<?php

namespace Webium\IndeedApply\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\Security\Permission;

class IndeedApplyLog extends DataObject
{
	private static $table_name = 'IndeedApplyLog';

	private static $db = [
		'RequestMethod'   => DBVarchar::class . '(10)',
		'RequestIP'       => DBVarchar::class . '(50)',
		'RequestHeaders'  => DBText::class,
		'RequestBody'     => DBText::class,
		'SignatureValid'  => DBBoolean::class,
		'ResponseCode'    => DBInt::class,
		'ResponseMessage' => DBText::class,
		'Success'         => DBBoolean::class,
		'ErrorMessage'    => DBText::class,
	];

	private static $has_one = [
		'IndeedApply' => IndeedApply::class,
	];

	private static $summary_fields = [
		'Created',
		'RequestMethod',
		'RequestIP',
		'SignatureValid.Nice',
		'Success.Nice',
		'IndeedApply.CandidateFullName' => 'Candidate',
		'IndeedApply.CandidateFirstName' => 'First Name',
		'IndeedApply.CandidateLastName' => 'Last Name',
	];

	private static $default_sort = 'Created DESC';

	/**
	 * Get field labels for translation
	 *
	 * @param bool $includerelations Whether to include relation labels
	 * @return array
	 */
	public function fieldLabels($includerelations = true)
	{
		$labels = parent::fieldLabels($includerelations);

		$labels['RequestMethod'] = _t(__CLASS__ . '.RequestMethod', 'Method');
		$labels['RequestIP'] = _t(__CLASS__ . '.RequestIP', 'IP Address');
		$labels['RequestHeaders'] = _t(__CLASS__ . '.RequestHeaders', 'Request Headers');
		$labels['RequestBody'] = _t(__CLASS__ . '.RequestBody', 'Request Body');
		$labels['SignatureValid'] = _t(__CLASS__ . '.SignatureValid', 'Signature Valid');
		$labels['SignatureValid.Nice'] = _t(__CLASS__ . '.SignatureValid', 'Signature Valid');
		$labels['ResponseCode'] = _t(__CLASS__ . '.ResponseCode', 'Response Code');
		$labels['ResponseMessage'] = _t(__CLASS__ . '.ResponseMessage', 'Response Message');
		$labels['Success'] = _t(__CLASS__ . '.Success', 'Success');
		$labels['Success.Nice'] = _t(__CLASS__ . '.Success', 'Success');
		$labels['ErrorMessage'] = _t(__CLASS__ . '.ErrorMessage', 'Error Message');
		$labels['IndeedApply'] = _t(__CLASS__ . '.IndeedApply', 'Application');
		$labels['Created'] = _t(__CLASS__ . '.Created', 'Date/Time');
		$labels['IndeedApply.CandidateFullName'] = _t(__CLASS__ . '.Candidate', 'Candidate');
		$labels['IndeedApply.CandidateFirstName'] = _t(__CLASS__ . '.CandidateFirstName', 'First Name');
		$labels['IndeedApply.CandidateLastName'] = _t(__CLASS__ . '.CandidateLastName', 'Last Name');

		return $labels;
	}

	/**
	 * Get CMS fields for viewing logs
	 * All fields are read-only for audit purposes
	 *
	 * @return FieldList
	 */
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		// Set rows BEFORE making fields readonly
		if ($requestHeaders = $fields->dataFieldByName('RequestHeaders')) {
			$requestHeaders->setRows(10);
		}

		if ($requestBody = $fields->dataFieldByName('RequestBody')) {
			$requestBody->setRows(15);
		}

		if ($responseMessage = $fields->dataFieldByName('ResponseMessage')) {
			$responseMessage->setRows(10);
		}

		// Make all fields readonly
		$fields->makeFieldReadonly('RequestMethod');
		$fields->makeFieldReadonly('RequestIP');
		$fields->makeFieldReadonly('RequestHeaders');
		$fields->makeFieldReadonly('RequestBody');
		$fields->makeFieldReadonly('SignatureValid');
		$fields->makeFieldReadonly('ResponseCode');
		$fields->makeFieldReadonly('ResponseMessage');
		$fields->makeFieldReadonly('Success');
		$fields->makeFieldReadonly('ErrorMessage');

		return $fields;
	}

	/**
	 * Check if member can view this log
	 *
	 * @param Member|null $member
	 * @return bool
	 */
	public function canView($member = null)
	{
		return Permission::check('CMS_ACCESS_IndeedApplyAdmin', 'any', $member);
	}

	/**
	 * Check if member can edit this log
	 * Note: Logs are read-only in the CMS interface for audit purposes
	 *
	 * @param Member|null $member
	 * @return bool
	 */
	public function canEdit($member = null)
	{
		// Allow viewing in CMS but all fields are read-only via getCMSFields()
		return Permission::check('CMS_ACCESS_IndeedApplyAdmin', 'any', $member);
	}

	/**
	 * Check if member can delete this log
	 * Only administrators can delete logs
	 *
	 * @param Member|null $member
	 * @return bool
	 */
	public function canDelete($member = null)
	{
		return Permission::check('ADMIN', 'any', $member);
	}

	/**
	 * Check if member can create new logs
	 * Note: Logs are automatically created by the system
	 *
	 * @param Member|null $member
	 * @param array $context
	 * @return bool
	 */
	public function canCreate($member = null, $context = [])
	{
		return true; // System needs to create logs
	}
}
