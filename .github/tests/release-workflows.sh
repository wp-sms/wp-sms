#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
ASSETS_WORKFLOW="$ROOT/.github/workflows/assets.yml"
DEPLOY_WORKFLOW="$ROOT/.github/workflows/deploy.yml"

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

    for workflow in "$ASSETS_WORKFLOW" "$DEPLOY_WORKFLOW" "$ROOT/.github/workflows/testing.yml"; do
        while IFS= read -r use; do
            [[ "$use" == ./* ]] && continue
            [[ "$use" =~ @[0-9a-f]{40}$ ]] || fail "$workflow uses a mutable action reference: $use"
        done < <(sed -nE 's/^[[:space:]]*uses:[[:space:]]*([^[:space:]#]+).*/\1/p' "$workflow")
    done
}

check_pinned_actions

echo "Release workflow regression tests passed."
