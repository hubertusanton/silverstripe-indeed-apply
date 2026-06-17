# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.12]

### Security
- Signature verification is now enforced automatically whenever `INDEED_APPLY_API_SECRET` is configured. Previously an invalid or missing signature was only rejected when `INDEED_APPLY_REQUIRE_SIGNATURE` was explicitly enabled, so unsigned POSTs were accepted by default even with a secret set — allowing arbitrary application injection.

### Deprecated
- `INDEED_APPLY_REQUIRE_SIGNATURE` is deprecated. It can no longer disable verification once a secret is present and has no effect without one.

## [1.0.0] - 2024-11-21

### Added
- Initial release
- POST endpoint at `/indeed-apply` for receiving Indeed Apply applications
- HMAC-SHA1 signature verification for authenticating Indeed requests
- Environment-based configuration via `.env` file
- Comprehensive request logging with `IndeedApplyLog`
- CMS interface (ModelAdmin) for managing applications and viewing logs
- Support for nested JSON structure from Indeed (job, applicant objects)
- Base64 resume file handling
- Custom questions storage as JSON
- Internationalization support (English and Dutch)
- Type-safe database field definitions using class constants
- Read-only log viewing in CMS
- Configurable endpoint routing

### Security
- HMAC-SHA1 signature verification with timing-safe comparison
- Configurable signature requirement via `INDEED_APPLY_REQUIRE_SIGNATURE`
- API secret stored in environment variables

[1.0.0]: https://github.com/webium/silverstripe-indeed-apply/releases/tag/v1.0.0