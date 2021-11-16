<?php

namespace LimeSurvey\Helpers\Update;

class Update_412 extends DatabaseUpdateBase
{
    public function run()
    {
            $sSurveyGroupQuery = "SELECT gsid  from {{surveys_groups}} order by gsid";
            $aGroups = $oDB->createCommand($sSurveyGroupQuery)->queryColumn();
            $sSurveyGroupSettingsQuery = "SELECT gsid  from {{surveys_groupsettings}} order by gsid";
            $aGroupSettings = $oDB->createCommand($sSurveyGroupSettingsQuery)->queryColumn();
            foreach ($aGroups as $group) {
                if (!array_key_exists($group, $aGroupSettings)) {
                    $settings = new SurveysGroupsettings();
                    $settings->setToInherit();
                    $settings->gsid = $group;
                    $oDB->createCommand()->insert("{{surveys_groupsettings}}", $settings->attributes);
                }
            }
    }
}