#!/usr/bin/env bash

export PATH
PATH="$(pwd)/vendor/bin:$PATH"

# Fix for Travis not specifying a range if testing the first commit of
# a new branch on push
if [ -z "$TRAVIS_COMMIT_RANGE" ]; then
    TRAVIS_COMMIT_RANGE="$(git rev-parse --verify --quiet "${TRAVIS_COMMIT}^1")...${TRAVIS_COMMIT}"
fi

if [ "$TEST_SUITE" = "syntax" ] || [ "$TEST_SUITE" = "style" ]; then
    # Check whether the start of the commit range is available.
    # If it is not available, try fetching the complete history.
    commit_range_start="$(echo "$TRAVIS_COMMIT_RANGE" | sed -E 's/^([a-fA-F0-9]+).*/\1/')"
    if ! git show --format='' --no-patch "$commit_range_start" &>/dev/null; then
        git fetch --unshallow

        # If it's still unavailable (likely due a push build caused by a force push),
        # tests based on what has changed cannot be run.
        if ! git show --format='' --no-patch "$commit_range_start" &>/dev/null; then
            echo "Could not find commit range start ($commit_range_start)." >&2
            echo "Tests based on changed files cannot run." >&2
            exit 1
        fi
    fi

    # Get the files changed by this commit (excluding deleted files).
    files_changed=()
    while IFS= read -r -d $'\0' file; do
        files_changed+=("$file")
    done < <(git diff --name-only --diff-filter=da -z "$TRAVIS_COMMIT_RANGE")

    # Separate the changed files by language.
    php_files_changed=()
    for file in "${files_changed[@]}"; do
        if [[ "$file" == *.php ]]; then
            php_files_changed+=("$file")
        fi
    done

    # Get any added files by language
    php_files_added=()
    while IFS= read -r -d $'\0' file; do
        if [[ "$file" == *.php ]]; then
            php_files_added+=("$file")
        fi
    done < <(git diff --name-only --diff-filter=A -z "$TRAVIS_COMMIT_RANGE")
fi

# Perform a test set based on the value of $TEST_SUITE.
build_exit_value=0
if [ "$TEST_SUITE" = "syntax" ]; then
    for file in "${php_files_changed[@]}" "${php_files_added[@]}"; do
        php -l "$file" >/dev/null
        if [ $? != 0 ]; then
            build_exit_value=2
        fi
    done
elif [ "$TEST_SUITE" = "style" ]; then
    for file in "${php_files_changed[@]}" "${php_files_added[@]}"; do
        phpcs "$file"
        if [ $? != 0 ]; then
            build_exit_value=2
        fi
    done
else
    echo "Invalid value for \$TEST_SUITE: $TEST_SUITE" >&2
    build_exit_value=1
fi

exit $build_exit_value
