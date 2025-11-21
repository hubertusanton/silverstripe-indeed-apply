<?php

namespace Webium\IndeedApply\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use Webium\IndeedApply\Models\IndeedApply;
use Webium\IndeedApply\Models\IndeedApplyLog;

/**
 * Indeed Apply Admin interface
 * Provides CMS interface for managing Indeed Apply applications and viewing logs
 */
class IndeedApplyAdmin extends ModelAdmin
{
    private static $url_segment = 'indeed-apply-admin';

    private static $menu_icon_class = 'font-icon-info-circled';

    private static $managed_models = [
        IndeedApply::class,
        IndeedApplyLog::class,
    ];

    /**
     * Customize GridField configuration for Indeed Apply models
     * - Removes export, import, and print buttons
     * - Prevents manual creation of applications and logs (should come from Indeed)
     *
     * @param int|null $id
     * @param FieldList|null $fields
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

        if ($gridField) {
            $config = $gridField->getConfig();

			$config->removeComponentsByType([
					GridFieldExportButton::class,
					GridFieldImportButton::class,
					GridFieldPrintButton::class,
				]
			);

            // For IndeedApplyLog: allow viewing but not creating new logs manually
			// For IndeedApply: don't allow creating new apply item manually, it should come from Indeed
            if ($this->modelClass === IndeedApplyLog::class || $this->modelClass === IndeedApply::class) {
                $config->removeComponentsByType(GridFieldAddNewButton::class);
            }

        }

        return $form;
    }
}
