# Release

Releases are tag-driven and follow [Semantic Versioning](https://semver.org).

1. Update `CHANGELOG.md` — move `Unreleased` items under a new `## [X.Y.Z] - DATE`.
2. Commit, then tag:

   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```

3. `.github/workflows/release.yml` extracts that version's CHANGELOG section and
   publishes a GitHub release with it as the body.

Every release must carry a human-readable description sourced from the CHANGELOG
— never a bare "see CHANGELOG" stub.

[← Docs index](../README.md#documentation)
