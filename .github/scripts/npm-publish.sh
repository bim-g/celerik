#!/usr/bin/env bash
# Publishes the package in the current directory to npm, for the Release and Publish to npm
# workflows. Usage: npm-publish.sh VERSION
#
# Two ways to authenticate, no one-time password in either:
# - Trusted publishing (recommended): on npmjs.com, @caelrik/ui > Settings > Trusted
#   publishing, add GitHub Actions with this repository and the workflow file release.yml
#   (npm takes one workflow: switch it to npm-publish.yml to publish a tag again), then set
#   the repository variable NPM_TRUSTED_PUBLISHING to true.
#   npm trades the job's OIDC token for a short-lived one: no secret, no 2FA prompt, and the
#   provenance comes with it. npm only offers it once the package exists on npm.
# - NPM_TOKEN: an npm granular access token, read and write on the @caelrik scope (not
#   "only select packages": a package that is not on npm yet cannot be selected), with
#   "Bypass two-factor authentication" ticked. Without that box, npm answers EOTP.
set -euo pipefail

VERSION="$1"
name=$(node -p "require('./package.json').name")

# Trusted publishing needs npm 11.5.1 or later.
npm install -g npm@11 --no-audit --no-fund > /dev/null
echo "npm $(npm --version)"

if [[ "${NPM_TRUSTED_PUBLISHING:-}" == true ]]; then
  # Without a token, npm uses the OIDC exchange.
  unset NODE_AUTH_TOKEN
elif [[ -z "${NODE_AUTH_TOKEN:-}" ]]; then
  echo "::error::Set the repository variable NPM_TRUSTED_PUBLISHING to true (trusted publishing), or add an NPM_TOKEN secret."
  exit 1
fi

# Pre-releases go to the "next" tag, so they never become "latest"; so does a version older
# than the one npm has as "latest" (0.7.0 published after 0.7.1), under "previous".
latest=$(npm view "$name" dist-tags.latest 2> /dev/null || true)
tag=latest
if [[ "$VERSION" == *-* ]]; then
  tag=next
elif [[ -n "$latest" && "$(printf '%s\n%s\n' "$latest" "$VERSION" | sort -V | tail -1)" != "$VERSION" ]]; then
  tag=previous
fi

set +e
npm publish --provenance --access public --tag "$tag" 2>&1 | tee npm-publish.log
status=${PIPESTATUS[0]}
set -e

if [[ $status -ne 0 ]]; then
  if grep -q 'EOTP' npm-publish.log; then
    echo "::error::npm asked for a one-time password: NPM_TOKEN does not bypass 2FA. Create a granular access token with \"Bypass two-factor authentication\" ticked, or switch to trusted publishing (see .github/scripts/npm-publish.sh)."
  elif grep -qE 'E404|ENEEDAUTH|E401' npm-publish.log && [[ "${NPM_TRUSTED_PUBLISHING:-}" == true ]]; then
    echo "::error::npm refused the OIDC token: check the trusted publisher of $name on npmjs.com (owner, repository and workflow file must match this run)."
  fi
  exit "$status"
fi
