#!/usr/bin/env bash

# Exit on errors
set -e

# Download helper function (moved to the top to avoid the command not found error)
download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    else
        echo "Error: Neither curl nor wget is installed."
        exit 1
    fi
}

# Exit if insufficient arguments
if [ $# -lt 3 ]; then
    echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

# Determine WP_TESTS_TAG based on WP_VERSION
if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
    WP_BRANCH=${WP_VERSION%\-*}
    WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
    WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
    if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
        WP_TESTS_TAG="tags/${WP_VERSION%??}"
    else
        WP_TESTS_TAG="tags/$WP_VERSION"
    fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
else
    download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
    LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
    if [[ -z "$LATEST_VERSION" ]]; then
        echo "Latest WordPress version could not be found"
        exit 1
    fi
    WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

# Check if svn is installed
check_svn_installed() {
    if ! command -v svn > /dev/null; then
        echo "Error: svn is not installed. Please install svn and try again."
        exit 1
    fi
}

# Install WP-CLI if not installed
install_wp_cli() {
    if ! wp --version; then
        echo "WP-CLI is not installed. Installing WP-CLI..."
        curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x wp-cli.phar
        mv wp-cli.phar /usr/local/bin/wp
        echo "WP-CLI installed successfully."
    else
        echo "WP-CLI is already installed."
    fi
}

# Install WordPress using wp-cli
install_wp() {
    if [ ! -d $WP_CORE_DIR ]; then
        echo "Executing: wp core download --version=${WP_VERSION} --path=${WP_CORE_DIR}"
        wp core download --version=${WP_VERSION} --path=${WP_CORE_DIR}
        echo "WordPress installed successfully."
    else
        echo "WordPress is already installed."
    fi
}

# Create wp-config.php if necessary
create_wp_config() {
    if [ ! -f $WP_CORE_DIR/wp-config.php ]; then
        echo "wp-config.php not found. Creating wp-config.php..."
        echo "Executing: wp config create --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --path=${WP_CORE_DIR}"
        wp config create --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --path=${WP_CORE_DIR}
        echo "wp-config.php created successfully."
    else
        echo "wp-config.php already exists."
    fi
}

# Create the database if necessary using wp-cli
install_db() {
    if [ ${SKIP_DB_CREATE} = "true" ]; then
        echo "Skipping database creation."
        return 0
    fi

    # Check if the database exists using wp-cli
    if ! wp db check --path=${WP_CORE_DIR}; then
        echo "Executing: wp db create --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --path=${WP_CORE_DIR}"
        wp db create --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --path=${WP_CORE_DIR}
        echo "Database '$DB_NAME' created successfully."
    else
        echo "Database '$DB_NAME' already exists."
    fi
}

# Install WordPress if it's not installed
install_wp_if_needed() {
    # Check if WordPress is installed, if not, run the wp-cli install command
    if ! wp core is-installed --path=${WP_CORE_DIR}; then
        echo "WordPress is not installed. Installing WordPress..."
        echo "Executing: wp core install --url=\"http://localhost\" --title=\"WordPress\" --admin_user=\"admin\" --admin_password=\"admin\" --admin_email=\"admin@example.com\" --path=${WP_CORE_DIR}"
        wp core install --url="http://localhost" --title="WordPress" --admin_user="admin" --admin_password="admin" --admin_email="admin@example.com" --path=${WP_CORE_DIR}
        echo "WordPress installed successfully."
    else
        echo "WordPress is already installed."
    fi
}

# Install WordPress test suite
install_test_suite() {
    # Portable in-place argument for both GNU sed and Mac OSX sed
    if [[ $(uname -s) == 'Darwin' ]]; then
        local ioption='-i.bak'
    else
        local ioption='-i'
    fi

    # Set up testing suite if it doesn't yet exist
    if [ ! -d $WP_TESTS_DIR ]; then
        mkdir -p $WP_TESTS_DIR
        rm -rf $WP_TESTS_DIR/{includes,data}
        check_svn_installed
        echo "Executing: svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes"
        svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
        echo "Executing: svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data"
        svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
        echo "Test suite installed successfully."
    else
        echo "Test suite is already installed."
    fi

    if [ ! -f wp-tests-config.php ]; then
        download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
        # Adjust wp-tests-config.php file
        WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
        sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
        echo "wp-tests-config.php file created and configured."
    fi
}

# Install WooCommerce via wp-cli
install_woocommerce() {
    echo 'Installing WooCommerce...'
    echo "Executing: wp plugin install woocommerce --activate --path=${WP_CORE_DIR}"
    wp plugin install woocommerce --path=${WP_CORE_DIR}
    echo "WooCommerce installed and activated successfully."
}

# Execute the functions in sequence
install_wp_cli
install_wp
create_wp_config
install_db
install_wp_if_needed
install_test_suite
install_woocommerce

echo "WordPress unit test environment set up successfully!"