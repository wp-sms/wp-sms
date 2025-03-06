#!/usr/bin/env bash

# Exit on errors
set -e

# Helper function for downloading files
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
    echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
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

echo "============================================================"
echo "        Setting up WordPress Unit Test Environment"
echo "============================================================"

# Check if svn is installed
check_svn_installed() {
    if ! command -v svn > /dev/null; then
        echo "Error: svn is not installed. Please install svn and try again."
        exit 1
    fi
}

# Install WP-CLI if not installed
install_wp_cli() {
    echo "=== Checking WP-CLI ==="
    if ! wp --version > /dev/null 2>&1; then
        echo "WP-CLI not found. Installing WP-CLI..."
        curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x wp-cli.phar
        mv wp-cli.phar /usr/local/bin/wp
        echo "WP-CLI installed successfully."
    else
        echo "WP-CLI is already installed."
    fi
    echo "------------------------------------------------------------"
}

# Install WordPress
install_wp() {
    echo "=== Installing WordPress ==="
    if [ ! -d "$WP_CORE_DIR" ]; then
        echo "Downloading WordPress version: $WP_VERSION"
        wp core download --version="${WP_VERSION}" --path="${WP_CORE_DIR}"
        echo "WordPress installed successfully in: $WP_CORE_DIR"
    else
        echo "WordPress is already installed at: $WP_CORE_DIR"
    fi
    echo "------------------------------------------------------------"
}

# Create wp-config.php
create_wp_config() {
    echo "=== Configuring WordPress ==="
    if [ ! -f "$WP_CORE_DIR/wp-config.php" ]; then
        echo "Creating wp-config.php..."
        wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --path="${WP_CORE_DIR}"
        echo "wp-config.php created successfully."
    else
        echo "wp-config.php already exists."
    fi
    echo "------------------------------------------------------------"
}

# Create the database
install_db() {
    echo "=== Setting Up Database ==="
    if [ "$SKIP_DB_CREATE" = "true" ]; then
        echo "Skipping database creation."
    else
        if ! wp db check --path="${WP_CORE_DIR}" > /dev/null 2>&1; then
            echo "Creating database: $DB_NAME"
            wp db create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --path="${WP_CORE_DIR}"
            echo "Database '$DB_NAME' created successfully."
        else
            echo "Database '$DB_NAME' already exists and is ready."
        fi
    fi
    echo "------------------------------------------------------------"
}

# Install WordPress if needed
install_wp_if_needed() {
    echo "=== Checking WordPress Installation ==="
    if ! wp core is-installed --path="${WP_CORE_DIR}" > /dev/null 2>&1; then
        echo "Installing WordPress..."
        wp core install --url="http://localhost" --title="WordPress Test Site" --admin_user="admin" --admin_password="admin" --admin_email="admin@example.com" --path="${WP_CORE_DIR}"
        echo "WordPress installed successfully."
    else
        echo "WordPress is already installed."
    fi
    echo "------------------------------------------------------------"
}

# Install test suite
install_test_suite() {
    echo "=== Setting Up Test Suite ==="
    if [ ! -d "$WP_TESTS_DIR" ]; then
        echo "Downloading test suite..."
        mkdir -p "$WP_TESTS_DIR"
        check_svn_installed
        svn export --quiet --ignore-externals https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
        svn export --quiet --ignore-externals https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ "$WP_TESTS_DIR/data"
        echo "Test suite installed successfully in: $WP_TESTS_DIR"
    else
        echo "Test suite is already installed."
    fi

    if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        echo "Configuring wp-tests-config.php..."
        download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s:youremptytestdbnamehere:$DB_NAME:" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s:yourusernamehere:$DB_USER:" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s:yourpasswordhere:$DB_PASS:" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
        echo "wp-tests-config.php configured successfully."
    fi
    echo "------------------------------------------------------------"
}

# Install required plugins (dependencies)
install_dependencies() {
    echo "=== Installing Required Plugins (Dependencies) ==="
    PLUGINS=("woocommerce" "debug-bar")

    for PLUGIN in "${PLUGINS[@]}"; do
        echo "Checking plugin: $PLUGIN..."
        if ! wp plugin is-installed "$PLUGIN" --path="${WP_CORE_DIR}" > /dev/null 2>&1; then
            echo "Downloading $PLUGIN..."
            wp plugin install "$PLUGIN" --path="${WP_CORE_DIR}"
            echo "$PLUGIN downloaded successfully."
        else
            echo "$PLUGIN is already installed."
        fi
    done
    echo "------------------------------------------------------------"
}

# Execute installation steps
install_wp_cli
install_wp
create_wp_config
install_db
install_wp_if_needed
install_test_suite
install_dependencies

echo "============================================================"
echo "    WordPress Unit Test Environment Setup Completed!"
echo "============================================================"
echo "🟢 WordPress Path: $WP_CORE_DIR"
echo "🟢 Test Suite Path: $WP_TESTS_DIR"
echo "🟢 Database Name: $DB_NAME"
echo "🟢 Plugins Downloaded: WooCommerce, Debug Bar"
echo "============================================================"
