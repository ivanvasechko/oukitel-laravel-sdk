# Contributing

Use Conventional Commits and open a pull request against `main`. Keep changes
focused, add regression tests and run the commands in README before submitting.
Contributions are provided under this repository's licence.

Never commit real credentials, device/product keys, account emails, tokens,
`authKey` values, Tuya access IDs/keys or UIDs. Fixtures must contain generated
values only. Protocol work must use a clean-room implementation and document
the provenance of bundled public app constants. Never copy reference implementation
code or publish account secrets.

## Release procedure

1. Wait for every CI matrix job and secret scan to pass.
2. Move the relevant `Unreleased` entries into `## [X.Y.Z] - YYYY-MM-DD`.
3. Commit through a pull request. Do not set a Composer `version` field.
4. Merge only after review and successful PR checks, then wait for the exact
   merged commit's **main push** CI run to succeed.
5. In GitHub Releases choose a new canonical `vX.Y.Z` tag at that tested commit,
   paste the matching changelog notes and publish. GPG signing is optional;
   lightweight tags created by GitHub's UI are accepted.
6. Confirm the read-only Release workflow succeeded and Packagist indexed the
   intended version. Its validator checks tag format, latest dated changelog
   entry, main ancestry and the latest exact-commit main push CI.

The workflow never creates releases or deployment records and has no write
permissions or deployment environment. GitHub Releases, Actions validation,
Packagist indexing and Deployments are different surfaces; an empty Deployments
page is normal for this library. Use manual workflow dispatch to revalidate an
existing tag if necessary.

Never move or replace a published tag. Packagist consumes Git tags independently:
only publish after CI and review, not to test a workflow. A failed validator
does not automatically retract a published release or Packagist version.

The maintainer must register the repository with Packagist and enable automatic
updates. Do not publish an empty placeholder release to reserve a name.
