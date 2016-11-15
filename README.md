# xero-schemas
Machine generated, machine-readable schemas for the Xero APIs

These schemas are currently being used in the `2.0` branch of [xero-php](https://github.com/calcinai/xero-php), and are designed as a source of truth for codegens in other languages since Xero's are incomplete and often out of date.

At this point the schemas are 100% generated from the developer documentation, but there is provision for an 'overrides' file to help out where the documentation is not completely correct or indicative of how it actually works (namely the behaviour around `TrackingCategories`).

## APIs supported
* Accounting API
* Payroll API (AU)
* Payroll API (US)
* Files API
* Assets API

The generated OpenAPI/Swagger files in the `schemas` folder.

## Installation and Generation

Installation can be done with a composer install, following that you can execute `./bin/generate` and the schemas will be updated.

## Issues and PRs

Raising issues is welcome, and feel free to submit PRs, but please don't submit changes that were not made directly from the generator or overrides file as they'll be impossible to track.

## OAuth 1.0

Unfortunately, the spec doesn't allow OAuth1, so there's not quite enough in the schemas to make a fully generatable API. 
