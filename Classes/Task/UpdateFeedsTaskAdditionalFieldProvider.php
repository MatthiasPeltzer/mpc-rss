<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Task;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Additional fields provider for UpdateFeedsTask
 * 
 * Provides configuration options in the TYPO3 Scheduler backend module.
 */
class UpdateFeedsTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * Gets additional fields to render in the form to add/edit a task
     * 
     * @param array $taskInfo Values of the fields from the add/edit task form
     * @param AbstractTask|null $task The task object being edited. Null when adding a task!
     * @param SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     * @return array A two dimensional array, ['fieldName' => ['code' => 'HTML', 'label' => 'Label']]
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];

        // Cache Lifetime field
        if (empty($taskInfo['cacheLifetime'])) {
            if ($task instanceof UpdateFeedsTask) {
                $taskInfo['cacheLifetime'] = $task->cacheLifetime;
            } else {
                $taskInfo['cacheLifetime'] = 3600;
            }
        }

        $fieldId = 'task_cacheLifetime';
        $fieldCode = '<input type="number" class="form-control" name="tx_scheduler[cacheLifetime]" id="' . $fieldId . '" value="' . htmlspecialchars((string)$taskInfo['cacheLifetime']) . '" min="0" step="60" />';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => 'Cache Lifetime (seconds)',
            'cshKey' => '',
            'cshLabel' => $fieldId,
        ];

        // Clear Cache checkbox
        if (empty($taskInfo['clearCache'])) {
            if ($task instanceof UpdateFeedsTask) {
                $taskInfo['clearCache'] = $task->clearCache;
            } else {
                $taskInfo['clearCache'] = false;
            }
        }

        $fieldId = 'task_clearCache';
        $checked = !empty($taskInfo['clearCache']) ? ' checked="checked"' : '';
        $fieldCode = '<input type="checkbox" class="form-check-input" name="tx_scheduler[clearCache]" id="' . $fieldId . '" value="1"' . $checked . ' />';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => 'Clear cache before updating',
            'cshKey' => '',
            'cshLabel' => $fieldId,
        ];

        return $additionalFields;
    }

    /**
     * Validates the additional fields' values
     * 
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     * @return bool True if validation was ok (or selected class is not relevant), false otherwise
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        // Validate cache lifetime - just ensure it's not negative
        $cacheLifetime = (int)($submittedData['cacheLifetime'] ?? 0);
        if ($cacheLifetime < 0) {
            return false;
        }

        return true;
    }

    /**
     * Takes care of saving the additional fields' values
     * 
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param AbstractTask $task Reference to the scheduler backend module
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof UpdateFeedsTask) {
            $task->cacheLifetime = (int)($submittedData['cacheLifetime'] ?? 3600);
            $task->clearCache = !empty($submittedData['clearCache']);
        }
    }
}

