<?php

namespace Webium\IndeedApply\Models;

use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

class IndeedApply extends DataObject implements PermissionProvider
{
	private static $table_name = 'IndeedApply';

	private static $db = [
		'JobTitle'        => DBVarchar::class . '(255)',
		'JobId'           => DBVarchar::class . '(255)',
		'JobCompanyName'  => DBVarchar::class . '(255)',
		'JobLocation'     => DBVarchar::class . '(255)',
		'JobUrl'          => DBText::class,

		// Candidate information
		'CandidateName'   => DBVarchar::class . '(255)',
		'CandidateEmail'  => DBVarchar::class . '(255)',
		'CandidatePhone'  => DBVarchar::class . '(100)',

		// Application details
		'CoverLetter'     => DBText::class,
		'CustomQuestions' => DBText::class, // JSON storage for custom questions/answers

		// Status
		'IsProcessed'     => DBBoolean::class,
		'ProcessedDate'   => DBDatetime::class,
		'Notes'           => DBText::class,

		// Raw data for debugging
		'RawPostData'     => DBText::class,
	];

	private static $has_one = [
		'Resume' => File::class,
	];

	private static $owns = [
		'Resume',
	];

	private static $summary_fields = [
		'Created',
		'CandidateName',
		'CandidateEmail',
		'JobTitle',
		'IsProcessed.Nice',
	];

	private static $default_sort = 'Created DESC';

	private static $defaults = [
		'IsProcessed' => false,
	];

	/**
	 * Get field labels for translation
	 *
	 * @param bool $includerelations Whether to include relation labels
	 * @return array
	 */
	public function fieldLabels($includerelations = true)
	{
		$labels = parent::fieldLabels($includerelations);

		$labels['JobTitle'] = _t(__CLASS__ . '.JobTitle', 'Job Title');
		$labels['JobId'] = _t(__CLASS__ . '.JobId', 'Job ID');
		$labels['JobCompanyName'] = _t(__CLASS__ . '.JobCompanyName', 'Company Name');
		$labels['JobLocation'] = _t(__CLASS__ . '.JobLocation', 'Location');
		$labels['JobUrl'] = _t(__CLASS__ . '.JobUrl', 'Job URL');
		$labels['CandidateName'] = _t(__CLASS__ . '.CandidateName', 'Candidate Name');
		$labels['CandidateEmail'] = _t(__CLASS__ . '.CandidateEmail', 'Email Address');
		$labels['CandidatePhone'] = _t(__CLASS__ . '.CandidatePhone', 'Phone Number');
		$labels['CoverLetter'] = _t(__CLASS__ . '.CoverLetter', 'Cover Letter');
		$labels['CustomQuestions'] = _t(__CLASS__ . '.CustomQuestions', 'Custom Questions');
		$labels['IsProcessed'] = _t(__CLASS__ . '.IsProcessed', 'Processed');
		$labels['IsProcessed.Nice'] = _t(__CLASS__ . '.IsProcessed', 'Processed');
		$labels['ProcessedDate'] = _t(__CLASS__ . '.ProcessedDate', 'Processed Date');
		$labels['Notes'] = _t(__CLASS__ . '.Notes', 'Notes');
		$labels['RawPostData'] = _t(__CLASS__ . '.RawPostData', 'Raw POST Data');
		$labels['Resume'] = _t(__CLASS__ . '.Resume', 'Resume');
		$labels['Created'] = _t(__CLASS__ . '.Created', 'Received On');

		return $labels;
	}

	/**
	 * Get CMS fields for applications
	 * Organizes fields into tabs: Job Information, Candidate, Processing, and Raw Data
	 *
	 * @return FieldList
	 */
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		// Get raw data fields before moving them
		$rawPostDataField = $fields->dataFieldByName('RawPostData');
		$customQuestionsField = $fields->dataFieldByName('CustomQuestions');

		// Remove from default location
		$fields->removeByName(['RawPostData', 'CustomQuestions']);

		// Rename Main tab
		$fields->findOrMakeTab('Root.Main', _t(__CLASS__ . '.Tab_Main', 'Job Information'));

		// Add job fields to Main tab
		$fields->addFieldsToTab('Root.Main', [
			$fields->dataFieldByName('JobTitle'),
			$fields->dataFieldByName('JobId'),
			$fields->dataFieldByName('JobCompanyName'),
			$fields->dataFieldByName('JobLocation'),
			$fields->dataFieldByName('JobUrl'),
		]);

		// Add candidate tab
		$fields->addFieldsToTab(
			'Root.Candidate',
			[
				$fields->dataFieldByName('CandidateName'),
				$fields->dataFieldByName('CandidateEmail'),
				$fields->dataFieldByName('CandidatePhone'),
				$fields->dataFieldByName('Resume'),
				$fields->dataFieldByName('CoverLetter'),
			]
		);
		$fields->findOrMakeTab('Root.Candidate')->setTitle(_t(__CLASS__ . '.Tab_Candidate', 'Candidate'));

		// Add processing tab
		$fields->addFieldsToTab(
			'Root.Processing',
			[
				$fields->dataFieldByName('IsProcessed'),
				$fields->dataFieldByName('ProcessedDate'),
				$fields->dataFieldByName('Notes'),
			]
		);
		$fields->findOrMakeTab('Root.Processing')->setTitle(_t(__CLASS__ . '.Tab_Processing', 'Processing'));

		// Raw data tab for debugging
		if ($rawPostDataField) {
			$rawPostDataField->setRows(20)->setReadonly(true);
			$fields->addFieldToTab('Root.RawData', $rawPostDataField);
		}

		if ($customQuestionsField) {
			$customQuestionsField->setRows(20)->setReadonly(true);
			$fields->addFieldToTab('Root.RawData', $customQuestionsField);
		}

		$fields->findOrMakeTab('Root.RawData')->setTitle(_t(__CLASS__ . '.Tab_RawData', 'Raw Data'));

		return $fields;
	}

	/**
	 * Check if member can view this application
	 *
	 * @param Member|null $member
	 * @return bool
	 */
	public function canView($member = null)
	{
		return Permission::check('CMS_ACCESS_IndeedApplyAdmin', 'any', $member);
	}

	/**
	 * Check if member can edit this application
	 *
	 * @param Member|null $member
	 * @return bool
	 */
	public function canEdit($member = null)
	{
		return Permission::check('CMS_ACCESS_IndeedApplyAdmin', 'any', $member);
	}

	/**
	 * Check if member can delete this application
	 *
	 * @param Member|null $member
	 * @return bool
	 */
	public function canDelete($member = null)
	{
		return Permission::check('CMS_ACCESS_IndeedApplyAdmin', 'any', $member);
	}

	/**
	 * Check if member can create new applications
	 * Note: Applications should only be created via Indeed POST requests
	 *
	 * @param Member|null $member
	 * @param array $context
	 * @return bool
	 */
	public function canCreate($member = null, $context = [])
	{
		return Permission::check('CMS_ACCESS_IndeedApplyAdmin', 'any', $member);
	}

	/**
	 * Provide permissions for Indeed Apply administration
	 *
	 * @return array
	 */
	public function providePermissions()
	{
		return [
			'CMS_ACCESS_IndeedApplyAdmin' => [
				'name'     => _t(__CLASS__ . '.PERMISSION_ACCESS', 'Access to Indeed Apply administration'),
				'category' => _t(__CLASS__ . '.PERMISSION_CATEGORY', 'Indeed Apply'),
			],
		];
	}
}
