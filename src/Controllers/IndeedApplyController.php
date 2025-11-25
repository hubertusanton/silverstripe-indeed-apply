<?php

namespace Webium\IndeedApply\Controllers;

use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;
use Webium\IndeedApply\Models\IndeedApply;
use Webium\IndeedApply\Models\IndeedApplyLog;

/**
 * Indeed Apply Controller
 *
 * Handles POST requests from Indeed Apply containing job applications.
 * Implements HMAC-SHA1 signature verification for security.
 *
 * Per Indeed guidelines:
 * - Always returns HTTP 2XX for successfully received applications
 * - Does not perform validation during POST (validation happens downstream)
 * - Does not redirect POST requests
 *
 * @package Webium\IndeedApply\Controllers
 */
class IndeedApplyController extends Controller
{
    /**
     * URL segment for the Indeed Apply endpoint
     * Configure this in _config/config.yml to use a different route
     * Default: 'indeed-apply' (accessible at /indeed-apply)
     */
    private static $url_segment = 'indeed-apply';

    private static $allowed_actions = [
        'index',
    ];

    /**
     * Get API secret from environment variable
     *
     * @return string|null
     */
    private function getApiSecret()
    {
        return Environment::getEnv('INDEED_APPLY_API_SECRET');
    }

    /**
     * Check if signature verification is required
     * Reads INDEED_APPLY_REQUIRE_SIGNATURE from environment
     *
     * @return bool
     */
    private function requiresSignatureVerification()
    {
        $requireSignature = Environment::getEnv('INDEED_APPLY_REQUIRE_SIGNATURE');
        return ($requireSignature === true || $requireSignature === 'true' || $requireSignature === '1');
    }

    /**
     * Handles Indeed Apply POST requests
     *
     * IMPORTANT: Per Indeed guidelines:
     * - Must return HTTP 2XX for all successfully received applications
     * - Do NOT perform validation on job application content during POST
     * - Do NOT redirect the POST request (301/302)
     * - Any validation should occur downstream of the POST
     */
    public function index(HTTPRequest $request)
    {
        $log = IndeedApplyLog::create();
        $log->RequestMethod = $request->httpMethod();
        $log->RequestIP = $request->getIP();
        $log->RequestHeaders = json_encode($request->getHeaders(), JSON_PRETTY_PRINT);

        try {
            // Only accept POST requests
            if (!$request->isPOST()) {
                return $this->errorResponse(
                    $log,
                    405,
                    'Method Not Allowed. Only POST requests are accepted.'
                );
            }

            // Indeed sends JSON in the raw body, not as form data
            $rawBody = $request->getBody();
            $log->RequestBody = $rawBody;

            // Verify HMAC-SHA1 signature if API secret is configured
            $signatureValid = $this->verifySignature($request, $rawBody);
            $log->SignatureValid = $signatureValid;

            // If signature verification is required and signature is invalid, reject
            if ($this->requiresSignatureVerification() && !$signatureValid) {
                $log->Success = false;
                $log->ResponseCode = 401;
                $log->ErrorMessage = 'Invalid signature';
                $log->write();

                return $this->errorResponse($log, 401, 'Invalid signature');
            }

            // Parse JSON - but still accept if parsing fails
            // We log it and return 200 to avoid Indeed retries
            $postData = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log the error but return 200 to prevent retries
                $log->Success = false;
                $log->ResponseCode = 200;
                $log->ErrorMessage = 'Invalid JSON (logged but accepted): ' . json_last_error_msg();
                $log->write();

                return $this->successResponse('Application received but requires manual review');
            }

            // Create IndeedApply record
            $apply = IndeedApply::create();

            // Map job information from nested 'job' object
            $jobData = $postData['job'] ?? [];
            $apply->JobTitle = $jobData['jobTitle'] ?? null;
            $apply->JobId = $jobData['jobId'] ?? null;
            $apply->JobCompanyName = $jobData['jobCompany'] ?? null;
            $apply->JobLocation = $jobData['jobLocation'] ?? null;
            $apply->JobUrl = $jobData['jobUrl'] ?? null;

            // Map candidate information from nested 'applicant' object
            $applicantData = $postData['applicant'] ?? [];
            $apply->CandidateFullName = $applicantData['fullName'] ?? null;
            $apply->CandidateFirstName = $applicantData['firstName'] ?? null;
            $apply->CandidateLastName = $applicantData['lastName'] ?? null;
            $apply->CandidateEmail = $applicantData['email'] ?? null;
            $apply->CandidatePhone = $applicantData['phoneNumber'] ?? null;

            // Map cover letter
            $apply->CoverLetter = $this->getPostValue($postData, 'coverLetter');

            // Store custom questions as JSON
            $customQuestions = $this->extractCustomQuestions($postData);
            if (!empty($customQuestions)) {
                $apply->CustomQuestions = json_encode($customQuestions, JSON_PRETTY_PRINT);
            }

            // Store raw post data for debugging
            $apply->RawPostData = json_encode($postData, JSON_PRETTY_PRINT);

            // Handle resume upload from nested applicant.resume.file (base64 encoded in JSON)
            if (isset($applicantData['resume']['file']) && !empty($applicantData['resume']['file'])) {
                $this->handleResumeUpload($apply, $applicantData['resume']['file']);
            }

            // Save the application
            $apply->write();

            // Link log to application
            $log->IndeedApplyID = $apply->ID;
            $log->Success = true;
            $log->ResponseCode = 200;
            $log->ResponseMessage = 'Application received successfully';
            $log->write();

            return $this->successResponse();

        } catch (\Exception $e) {
            // Log the error but return 200 to prevent Indeed retries
            // Per Indeed guidelines: always return 2XX for received applications
            $log->Success = false;
            $log->ResponseCode = 200;
            $log->ErrorMessage = 'Exception caught (logged but accepted): ' . $e->getMessage();
            $log->write();

            // Still return 200 to Indeed
            return $this->successResponse('Application received but encountered processing error');
        }
    }

    /**
     * Get value from POST data
     *
     * @param array $postData The decoded POST data
     * @param string $key The key to look for
     * @return mixed|null
     */
    private function getPostValue($postData, $key)
    {
        if (isset($postData[$key])) {
            return $postData[$key];
        }
        return null;
    }

    /**
     * Extract custom questions from POST data
     * Indeed uses "questionsAndAnswers" array in new integrations
     *
     * @param array $postData The decoded POST data
     * @return array Array of custom questions and answers
     */
    private function extractCustomQuestions($postData)
    {
        $customQuestions = [];

        // New format: questionsAndAnswers array
        if (isset($postData['questionsAndAnswers']) && is_array($postData['questionsAndAnswers'])) {
            $customQuestions = $postData['questionsAndAnswers'];
        }

        // Legacy format: look for question_* keys
        foreach ($postData as $key => $value) {
            if (strpos($key, 'question_') === 0 || strpos($key, 'customQuestion') === 0) {
                $customQuestions[$key] = $value;
            }
        }

        return $customQuestions;
    }

    /**
     * Handle resume file upload from base64 encoded data
     * Indeed sends resume as: {"fileName": "pdf-test.pdf", "contentType": "application/pdf", "data": "base64data"}
     *
     * @param IndeedApply $apply The application record
     * @param mixed $resumeData Resume data (string or array)
     * @return void
     */
    private function handleResumeUpload(IndeedApply $apply, $resumeData)
    {
        try {
            // Resume data can be a string (base64) or an array with metadata
            if (is_string($resumeData)) {
                // Simple base64 string
                $content = base64_decode($resumeData);
                $filename = 'resume_' . date('YmdHis') . '.pdf';
            } elseif (is_array($resumeData)) {
                // Array with fileName, contentType, and data (Indeed format)
                $content = base64_decode($resumeData['data'] ?? $resumeData['content'] ?? '');
                $filename = $resumeData['fileName'] ?? $resumeData['name'] ?? $resumeData['filename'] ?? ('resume_' . date('YmdHis') . '.pdf');
            } else {
                return;
            }

            if (empty($content)) {
                return;
            }

            // Create file in assets
            $file = File::create();
            $file->setFromString($content, 'Uploads/IndeedApply/Resumes/' . $filename);
            $file->write();

            $apply->ResumeID = $file->ID;

        } catch (\Exception $e) {
            // Log error but don't fail the entire request
            error_log('Resume upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify HMAC-SHA1 signature from Indeed Apply
     *
     * Indeed sends the signature in the X-Indeed-Signature header
     * Signature is computed using HMAC-SHA1 on the raw JSON body with the shared API secret
     *
     * @param HTTPRequest $request The HTTP request
     * @param string $rawBody The raw request body
     * @return bool True if signature is valid or not configured, false if invalid
     */
    private function verifySignature(HTTPRequest $request, $rawBody)
    {
        // Get API secret from helper method
        $apiSecret = $this->getApiSecret();

        // If no API secret is configured, skip verification
        if (empty($apiSecret)) {
            return true;
        }

        // Get the signature from the X-Indeed-Signature header
        // Try both lowercase and uppercase (Indeed sends lowercase, but headers should be case-insensitive)
        $receivedSignature = $request->getHeader('x-indeed-signature')
            ?: $request->getHeader('X-Indeed-Signature');

        // If no signature header is present, consider it invalid
        if (empty($receivedSignature)) {
            return false;
        }

        // Generate expected signature using HMAC-SHA1
        $expectedSignature = $this->generateSignature($rawBody, $apiSecret);

        // Compare signatures (timing-safe comparison)
        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Generate HMAC-SHA1 signature for the given message
     *
     * @param string $message The message to sign (raw JSON body)
     * @param string $secret The shared API secret
     * @return string Base64-encoded signature
     */
    private function generateSignature($message, $secret)
    {
        // Generate HMAC-SHA1 hash
        $hash = hash_hmac('sha1', $message, $secret, true);

        // Encode to base64
        return base64_encode($hash);
    }

    /**
     * Return success response
     * Always returns HTTP 200 as required by Indeed
     *
     * @param string $message Success message
     * @return HTTPResponse
     */
    private function successResponse($message = 'Application received successfully')
    {
        $response = HTTPResponse::create();
        $response->setStatusCode(200);
        $response->setBody(json_encode([
            'success' => true,
            'message' => $message
        ]));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * Return error response and log it
     *
     * @param IndeedApplyLog $log The log record to update
     * @param int $code HTTP status code
     * @param string $message Error message
     * @return HTTPResponse
     */
    private function errorResponse(IndeedApplyLog $log, $code, $message)
    {
        $log->Success = false;
        $log->ResponseCode = $code;
        $log->ErrorMessage = $message;
        $log->write();

        $response = HTTPResponse::create();
        $response->setStatusCode($code);
        $response->setBody(json_encode([
            'success' => false,
            'error'   => $message
        ]));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }
}
