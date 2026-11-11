#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
ASSETS_WORKFLOW="$ROOT/.github/workflows/assets.yml"
CHANGELOG_FILE="$ROOT/CHANGELOG.md"
DEPLOY_WORKFLOW="$ROOT/.github/workflows/deploy.yml"
GATEWAY_REGISTRY_WORKFLOW="$ROOT/.github/workflows/notify-gateway-registry.yml"
INSTALL_WP_TESTS="$ROOT/bin/install-wp-tests.sh"
PLUGIN_FILE="$ROOT/wp-sms.php"
README_FILE="$ROOT/readme.txt"
POT_FILE="$ROOT/languages/wp-sms.pot"

fail() {
    echo "FAIL: $*" >&2
    exit 1
}

extract_run_step() {
    local workflow=$1
    local step_name=$2

    awk -v target="      - name: $step_name" '
        $0 == target { found = 1; next }
        found && $0 == "        run: |" { script = 1; next }
        script && /^      - name:/ { exit }
        script {
            sub(/^          /, "")
            print
        }
    ' "$workflow"
}

run_assets_guard() {
    local stable=$1
    local live=$2
    local expected=$3
    local event_sha=${4:-1111111111111111111111111111111111111111}
    local master_sha=${5:-1111111111111111111111111111111111111111}
    local tmp rc output

    tmp=$(mktemp -d)
    mkdir -p "$tmp/workspace"
    printf 'Stable tag: %s\n' "$stable" > "$tmp/workspace/readme.txt"

    {
        cat <<EOF
curl() {
    if [[ "\$*" == *"plugins.svn.wordpress.org"* ]]; then
        printf '200'
    else
        printf '{"version":"%s"}' "$live"
    fi
}
git() {
    if [[ "\$*" == "ls-remote origin refs/heads/master" ]]; then
        printf '%s\trefs/heads/master\n' "$master_sha"
    else
        command git "\$@"
    fi
}
EOF
        extract_run_step "$ASSETS_WORKFLOW" "The readme is safe to publish"
    } > "$tmp/guard.sh"

    set +e
    output=$(cd "$tmp/workspace" && \
        GITHUB_OUTPUT="$tmp/github-output" \
        GITHUB_SHA="$event_sha" \
        bash "$tmp/guard.sh" 2>&1)
    rc=$?
    set -e

    case "$expected" in
        pass)
            [[ $rc -eq 0 ]] || fail "assets guard rejected stable=$stable live=$live: $output"
            grep -qx 'ok=yes' "$tmp/github-output" || fail "assets guard did not authorize stable=$stable live=$live"
            ;;
        fail)
            [[ $rc -ne 0 ]] || fail "assets guard authorized stable=$stable live=$live"
            ;;
        *) fail "unknown expectation: $expected" ;;
    esac

    rm -rf "$tmp"
}

run_assets_guard 7.2.11 7.2.11 pass
run_assets_guard 7.2.12 7.2.11 fail
run_assets_guard 7.2.10 7.2.11 fail
run_assets_guard 7.2.11 7.2.11 fail \
    aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
    bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb

run_deploy_guard() {
    local candidate=$1
    local live=$2
    local expected=$3
    local tmp rc output

    tmp=$(mktemp -d)
    extract_run_step "$DEPLOY_WORKFLOW" "Release is still safe to deploy" > "$tmp/step.sh"
    grep -q '[^[:space:]]' "$tmp/step.sh" || fail "deploy safety step is missing"

    {
        cat <<EOF
curl() {
    printf 'Stable tag: %s\n' "$live"
}
EOF
        cat "$tmp/step.sh"
    } > "$tmp/guard.sh"

    set +e
    output=$(TAG="v$candidate" bash "$tmp/guard.sh" 2>&1)
    rc=$?
    set -e

    case "$expected" in
        pass)
            [[ $rc -eq 0 ]] || fail "deploy guard rejected candidate=$candidate live=$live: $output"
            ;;
        fail)
            [[ $rc -ne 0 ]] || fail "deploy guard authorized candidate=$candidate live=$live"
            ;;
        *) fail "unknown expectation: $expected" ;;
    esac

    rm -rf "$tmp"
}

run_deploy_guard 7.2.12 7.2.11 pass
run_deploy_guard 7.2.12 7.2.12 pass
run_deploy_guard 7.2.12 7.2.13 fail

check_pinned_actions() {
    local workflow use

    for workflow in "$ASSETS_WORKFLOW" "$DEPLOY_WORKFLOW" "$GATEWAY_REGISTRY_WORKFLOW" "$ROOT/.github/workflows/testing.yml"; do
        while IFS= read -r use; do
            [[ "$use" == ./* ]] && continue
            [[ "$use" =~ @[0-9a-f]{40}$ ]] || fail "$workflow uses a mutable action reference: $use"
        done < <(sed -nE 's/^[[:space:]]*uses:[[:space:]]*([^[:space:]#]+).*/\1/p' "$workflow")
    done
}

check_pinned_actions

check_legacy_setup_skips_woocommerce() {
    local tmp calls

    tmp=$(mktemp -d)
    calls="$tmp/wp-calls"
    mkdir -p "$tmp/bin" "$tmp/wordpress" "$tmp/wordpress-tests-lib"
    touch "$tmp/wordpress/wp-config.php" "$tmp/wordpress-tests-lib/wp-tests-config.php"

    cat > "$tmp/bin/wp" <<'EOF'
#!/usr/bin/env bash
printf 'wp %s\n' "$*" >> "$TOOL_CALLS"
exit 0
EOF
    cat > "$tmp/bin/curl" <<'EOF'
#!/usr/bin/env bash
printf 'curl %s\n' "$*" >> "$TOOL_CALLS"
exit 99
EOF
    chmod +x "$tmp/bin/wp" "$tmp/bin/curl"

    PATH="$tmp/bin:$PATH" \
        TOOL_CALLS="$calls" \
        WP_CORE_DIR="$tmp/wordpress" \
        WP_TESTS_DIR="$tmp/wordpress-tests-lib" \
        bash "$INSTALL_WP_TESTS" wordpress_tests root root 127.0.0.1 6.6 true false >/dev/null

    if grep -q '^wp plugin install woocommerce' "$calls"; then
        fail "legacy textdomain setup installed WooCommerce despite opting out"
    fi

    if grep -q '^curl ' "$calls"; then
        fail "legacy textdomain setup used the network despite an existing test config"
    fi

    rm -rf "$tmp"
}

check_legacy_setup_skips_woocommerce

check_release_metadata() {
    local plugin_version stable_tag pot_version

    plugin_version=$(sed -nE 's/^[[:space:]]*\* Version:[[:space:]]*//p' "$PLUGIN_FILE" | head -1)
    stable_tag=$(sed -nE 's/^Stable tag:[[:space:]]*//p' "$README_FILE" | head -1)
    pot_version=$(grep -m1 'Project-Id-Version:' "$POT_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')

    [[ -n "$plugin_version" ]] || fail "plugin version is missing"
    [[ "$stable_tag" == "$plugin_version" ]] || fail "readme stable tag $stable_tag does not match plugin version $plugin_version"
    [[ "$pot_version" == "$plugin_version" ]] || fail "POT version $pot_version does not match plugin version $plugin_version"
}

check_release_metadata

check_release_changelog_parity() {
    local tmp

    tmp=$(mktemp -d)
    awk 'NR == 1 { next } /^- / { print; next } { exit }' "$CHANGELOG_FILE" > "$tmp/changelog"
    awk '
        $0 == "== Changelog ==" { in_changelog = 1; next }
        in_changelog && /^= v/ { in_release = 1; next }
        in_release && /^- / { print; next }
        in_release { exit }
    ' "$README_FILE" > "$tmp/readme"

    cmp -s "$tmp/changelog" "$tmp/readme" || fail "CHANGELOG.md and readme.txt have different current release notes"
    rm -rf "$tmp"
}

check_release_changelog_parity

echo "Release workflow regression tests passed."
