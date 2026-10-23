#!/bin/sh
# Make a fresh git worktree of this repo runnable: vendor, .env and the built
# frontend come from the main checkout, and the repo's pre-commit hook gets linked
# into the shared .git/hooks (the global hooks chain to it).
#
# vendor is a real copy (APFS clone, so it is cheap), never a symlink: a
# symlinked vendor resolves Composer's autoloader to the MAIN checkout's app/,
# so tests in the worktree silently run the main checkout's code.
#
# usage: scripts/worktree-setup.sh   (from inside the worktree)
set -eu

here="$(git rev-parse --show-toplevel)"
main="$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")"

hook="$(git rev-parse --path-format=absolute --git-common-dir)/hooks/pre-commit"
[ -e "$hook" ] || [ -L "$hook" ] || ln -s ../../scripts/githooks/pre-commit "$hook"

[ "$here" = "$main" ] && { echo "worktree-setup: main checkout, hooks linked, nothing to copy"; exit 0; }

for p in vendor public/build; do
  if [ ! -e "$here/$p" ]; then
    [ -e "$main/$p" ] || { echo "worktree-setup: $main/$p is missing (composer install / npm run build there first)" >&2; exit 1; }
    mkdir -p "$(dirname "$here/$p")"
    cp -Rc "$main/$p" "$here/$p"
  fi
done
[ -e "$here/.env" ] || cp "$main/.env" "$here/.env"

echo "worktree-setup: vendor, public/build and .env ready in $here"
