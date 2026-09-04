# Canonical repository status

`Yolol100/ACF-Text-Manager` is the canonical repository for ACF Page Text Manager.

## Ownership

This repository owns:

- active development;
- issues and security maintenance;
- release tags and packages;
- WordPress, PHP, ACF and spreadsheet compatibility;
- current README, changelog and migration guidance.

`Yolol100/Export-acf-to-csv` is a deprecated duplicate retained temporarily for history, rollback and unique-commit reconciliation.

## Reconciliation rules

- Compare commits and file hashes before copying anything from the deprecated repository.
- Import only a proven unique fix or historical record.
- Do not duplicate a change already present here under another commit.
- Preserve plugin slug, option keys, import/export contracts and release version parity.
- Validate any imported code with the existing quality and runtime gates.
- Archive the deprecated repository only after active users are redirected and the canonical package passes staging checks.

## Product boundary

This remains a product repository. It is not a Webactueel workflow controller, project source or permanent Orchestrator node. `wordpressqualityarchitect` owns code and release quality when this repository is audited or changed.
