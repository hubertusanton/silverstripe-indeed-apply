# SilverStripe Indeed Apply Module

This module enables receiving job applications from Indeed Apply via a POST endpoint.

## What does this module do?

This module:
- Provides an endpoint (`/indeed-apply`) where Indeed Apply applications can be sent
- Logs all incoming requests for debugging
- Stores applications in the database for further processing
- Provides a CMS interface for managing applications and logs

## Installation

1. Install via Composer:
```bash
composer require webium/silverstripe-indeed-apply
```

2. Run dev/build to create database tables:
```bash
vendor/bin/sake dev/build flush=1
```

## Database Tables

After installation, the following tables are created:
- `IndeedApply` - Contains the applications
- `IndeedApplyLog` - Contains logs of all requests

## Endpoint

The module creates a POST endpoint at:
```
https://yourdomain.com/indeed-apply
```

### Customizing the Endpoint URL

You can customize the endpoint URL by editing your project's config file:

**app/_config/indeed-apply-custom.yml:**
```yaml
---
Name: indeed-apply-custom
After: 'indeed-apply-config'
---
# Custom route configuration
SilverStripe\Control\Director:
  rules:
    'jobs/apply': 'Webium\IndeedApply\Controllers\IndeedApplyController'

# Update the url_segment to match
Webium\IndeedApply\Controllers\IndeedApplyController:
  url_segment: 'jobs/apply'
```

**Important:** Don't forget to update your Indeed Apply postUrl in your job feed to match the new endpoint:
```
https://yourdomain.com/jobs/apply
```

Run `/dev/build?flush=1` after changing the route.

## Authentication

The module supports HMAC-SHA1 signature verification for authenticating Indeed Apply POST requests.

### Configuration

Add the following to your project's `.env` file:

```env
# Indeed Apply Module Configuration
INDEED_APPLY_API_SECRET="your-api-secret-here"
```

**Environment Variables:**
- `INDEED_APPLY_API_SECRET` - Your shared API secret from Indeed Apply integration settings. As soon as this is set, signature verification is enforced: requests with an invalid or missing signature are rejected with HTTP 401.
- `INDEED_APPLY_REQUIRE_SIGNATURE` - **Deprecated.** Verification is now enforced automatically whenever a secret is configured. This flag only opts in to verification *before* a secret is set and has no effect once `INDEED_APPLY_API_SECRET` is present.

The controller reads these environment variables directly using `Environment::getEnv()`.

### How it works

1. Indeed Apply sends a POST request with an `x-indeed-signature` header
2. The signature is generated using HMAC-SHA1 on the raw JSON body with your shared API secret
3. The module verifies the signature matches the expected value
4. All requests are logged with signature validation status in `IndeedApplyLog`

### Security Options

Signature verification is tied to the presence of `INDEED_APPLY_API_SECRET`.

**Development mode** (no secret configured):
- Leave `INDEED_APPLY_API_SECRET` unset
- All requests are accepted regardless of signature
- Signature validation status is still logged for debugging
- Use this only for initial testing

**Production mode** (recommended):
```env
INDEED_APPLY_API_SECRET="your-api-secret-here"
```
- Verification is enforced automatically as soon as the secret is set
- Only requests with a valid `x-indeed-signature` are accepted
- Invalid or missing signatures return HTTP 401 Unauthorized
- Protects against unauthorized POST requests

### Getting Your API Secret

1. Log in to your Indeed Employer account
2. Go to Indeed Apply integration settings
3. Find the "Shared API Secret" with credential type "Indeed Apply"
4. Copy the secret and add it to your configuration

## XML Feed Configuration

Add the following field to your Indeed XML feed:

```xml
<indeed-apply-data><![CDATA[indeed-apply-apiToken=YOUR_API_TOKEN&indeed-apply-jobTitle=JOB_TITLE&indeed-apply-jobId=JOB_ID&indeed-apply-jobCompanyName=COMPANY_NAME&indeed-apply-jobLocation=LOCATION&indeed-apply-jobUrl=https%3A%2F%2Fyourdomain.com%2Fjob-url&indeed-apply-postUrl=https%3A%2F%2Fyourdomain.com%2Findeed-apply%2F&indeed-apply-name=firstlastname]]></indeed-apply-data>
```

**Important:**
- The `indeed-apply-postUrl` must point to your domain's `/indeed-apply/` endpoint
- **`indeed-apply-name` must be set to `firstlastname`** - This module requires separate `firstName` and `lastName` fields. Without this setting, Indeed only sends `fullName` and the module will return HTTP 400 for missing required fields.

## Expected Data

The module expects the following fields from Indeed:

### Job Information
- `jobTitle` - Job title
- `jobId` - Job/vacancy ID
- `jobCompanyName` - Company name
- `jobLocation` - Location
- `jobUrl` - URL to job posting

### Candidate Information
- `fullName` - Full name
- `firstName` - First name
- `lastName` - Last name
- `email` - Email address
- `phoneNumber` - Phone number
- `coverLetter` - Cover letter
- `resume` - CV (file upload)

### Custom Questions
All fields starting with `question_` or `customQuestion` are automatically stored as JSON.

## CMS Interface

After installation, a new menu item appears in the CMS: **Indeed Apply**

Here you can:
1. **Indeed Apply Applications** - View and manage applications
   - All received applications
   - Candidate information
   - CV downloads
   - Track status (processed/not processed)
   - Add notes

2. **Indeed Apply Logs** - View request logs
   - All received requests
   - Request headers and body
   - Response codes
   - Error messages
   - Logs are read-only

## Permissions

The module uses the following permission:
- `CMS_ACCESS_IndeedApplyAdmin` - Access to Indeed Apply administration

This can be assigned to user groups via Security > Groups.

## Processing Applications

Applications are stored with `IsProcessed = false`. You can later:
1. Manually process them in the CMS
2. Automatically process them via a custom script
3. Export to Excel for bulk processing

## Debugging

All requests are logged in `IndeedApplyLog`. Here you can see:
- When a request came in
- What the request body was
- Signature validation status (true/false)
- Whether it was successful
- Any error messages

The `SignatureValid` field in logs shows whether the HMAC-SHA1 signature was valid for each request.

## Technical Details

- **Namespace:** `Webium\IndeedApply`
- **Endpoint URL:** `/indeed-apply`
- **Accepted methods:** POST only
- **CSRF Protection:** Disabled for this endpoint (required for external POST requests)
- **File uploads:** Max 5MB, allowed extensions: pdf, doc, docx, txt, rtf

## Response Codes

The endpoint returns the following HTTP status codes:

| Code | Description |
|------|-------------|
| 200  | Application received successfully |
| 400  | Missing required fields in JSON payload |
| 401  | Invalid or missing signature (when `INDEED_APPLY_API_SECRET` is configured) |
| 405  | Method not allowed (only POST is accepted) |
| 404  | Job does not exist in the system (via `validateJobExists` extension hook) |
| 409  | Duplicate application: candidate has already applied for this job within the last 120 days |
| 410  | Job is expired or no longer published (via `validateJobExpired` extension hook) |
| 413  | Payload too large (exceeds PHP's `post_max_size` setting) |
| 422  | Unable to save application (valid JSON but can't be processed) |

### Required Fields (400)

The following fields are required in the JSON payload:

| Field | Required |
|-------|----------|
| `job.jobId` | Yes |
| `applicant.fullName` | Yes |
| `applicant.firstName` | Yes, when `indeed-apply-name` is set to `firstlastname` |
| `applicant.lastName` | Yes, when `indeed-apply-name` is set to `firstlastname` |
| `applicant.email` | Yes |
| `applicant.verified` | Yes |

If any required field is missing, the endpoint returns HTTP 400 with a list of missing fields.

### Duplicate Application Check (409)

The module automatically checks for duplicate applications based on `CandidateEmail` and `JobId`. If a candidate has already applied for the same job, the endpoint returns HTTP 409.

## Extension Hooks

The controller provides extension hooks for custom validation logic. These hooks allow you to validate JobIds against your ATS (Applicant Tracking System).

### validateJobExists Hook (404)

Use this hook to check if a JobId exists in your system. If the job doesn't exist at all, set the error to return HTTP 404.

### validateJobExpired Hook (410)

Use this hook to check if a job is expired or no longer published. If the job exists but is no longer available, set the error to return HTTP 410.

**Create an extension in your application:**

```php
// app/src/Extension/IndeedApplyJobValidator.php
namespace App\Extension;

use SilverStripe\Core\Extension;

class IndeedApplyJobValidator extends Extension
{
    /**
     * Check if job exists in the system (404 if not found)
     */
    public function validateJobExists(string $jobId, ?string &$error): void
    {
        $job = MyATSService::findJob($jobId);

        if (!$job) {
            $error = "Job {$jobId} does not exist";
        }
    }

    /**
     * Check if job is expired/unpublished (410 if expired)
     */
    public function validateJobExpired(string $jobId, ?string &$error): void
    {
        $job = MyATSService::findJob($jobId);

        if ($job && !$job->isPublished()) {
            $error = "Job {$jobId} is no longer available";
        }
    }
}
```

**Register in `app/_config/config.yml`:**

```yaml
Webium\IndeedApply\Controllers\IndeedApplyController:
  extensions:
    - App\Extension\IndeedApplyJobValidator
```

Run `dev/build flush=1` after adding the extension.

## Resume File Security

Resume files are uploaded to `Uploads/IndeedApply/Resumes/` and are automatically protected. Each uploaded resume has `CanViewType` set to `LoggedInUsers`, ensuring that only logged-in CMS users can access the files.

## Troubleshooting

### "Method Not Allowed" error
Check if Indeed is actually sending POST requests to the endpoint.

### Applications not being saved
1. Check the logs in `Indeed Apply Logs` in the CMS
2. Look at the `RequestBody` to see what data Indeed is sending
3. Verify that field names match

### CVs not uploading
1. Check file upload settings in `php.ini`
2. Verify that the `Uploads/IndeedApply/Resumes` directory has write permissions
3. Check logs for specific error messages

### Invalid signature errors (HTTP 401)
1. Verify your `INDEED_APPLY_API_SECRET` in `.env` matches the shared secret in your Indeed Apply integration settings
2. Check the `SignatureValid` field in `IndeedApplyLog` to see signature validation status
3. To accept all requests during initial testing, leave `INDEED_APPLY_API_SECRET` unset (verification is only enforced once a secret is configured)
4. Confirm the `X-Indeed-Signature` header is being sent by Indeed

## Requirements

- SilverStripe 4.x or 5.x

## Documentation

For more information about Indeed Apply, see:
https://docs.indeed.com/indeed-apply/add-indeed-apply

## License

MIT

## Maintainer

Webium - https://webium.nl
