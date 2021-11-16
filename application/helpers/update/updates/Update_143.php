<?php

namespace LimeSurvey\Helpers\Update;

class Update_143 extends DatabaseUpdateBase
{
    public function run()
    {
            addColumn('{{questions}}', 'parent_qid', 'integer NOT NULL default 0');
            addColumn('{{answers}}', 'scale_id', 'integer NOT NULL default 0');
            addColumn('{{questions}}', 'scale_id', 'integer NOT NULL default 0');
            addColumn('{{questions}}', 'same_default', 'integer NOT NULL default 0');
            dropPrimaryKey('answers');
            addPrimaryKey('answers', array('qid', 'code', 'language', 'scale_id'));

            $aFields = array(
                'qid' => "integer NOT NULL default 0",
                'scale_id' => 'integer NOT NULL default 0',
                'sqid' => 'integer  NOT NULL default 0',
                'language' => 'string(20) NOT NULL',
                'specialtype' => "string(20) NOT NULL default ''",
                'defaultvalue' => 'text',
            );
            $oDB->createCommand()->createTable('{{defaultvalues}}', $aFields);
            addPrimaryKey('defaultvalues', array('qid', 'specialtype', 'language', 'scale_id', 'sqid'));

            // -Move all 'answers' that are subquestions to the questions table
            // -Move all 'labels' that are answers to the answers table
            // -Transscribe the default values where applicable
            // -Move default values from answers to questions
            upgradeTables143();

            dropColumn('{{answers}}', 'default_value');
            dropColumn('{{questions}}', 'lid');
            dropColumn('{{questions}}', 'lid1');

            $aFields = array(
                'sesskey' => "string(64) NOT NULL DEFAULT ''",
                'expiry' => "datetime NOT NULL",
                'expireref' => "string(250) DEFAULT ''",
                'created' => "datetime NOT NULL",
                'modified' => "datetime NOT NULL",
                'sessdata' => 'text'
            );
            $oDB->createCommand()->createTable('{{sessions}}', $aFields);
            addPrimaryKey('sessions', array('sesskey'));
            $oDB->createCommand()->createIndex('sess2_expiry', '{{sessions}}', 'expiry');
            $oDB->createCommand()->createIndex('sess2_expireref', '{{sessions}}', 'expireref');
            // Move all user templates to the new user template directory
            echo "<br>" . sprintf(
                gT("Moving user templates to new location at %s..."),
                $sUserTemplateRootDir
            ) . "<br />";
            $hTemplateDirectory = opendir($sStandardTemplateRootDir);
            $aFailedTemplates = array();
            // get each entry
            while ($entryName = readdir($hTemplateDirectory)) {
                if (
                    !in_array($entryName, array('.', '..', '.svn')) && is_dir(
                        $sStandardTemplateRootDir . DIRECTORY_SEPARATOR . $entryName
                    ) && !Template::isStandardTemplate($entryName)
                ) {
                    if (
                        !rename(
                            $sStandardTemplateRootDir . DIRECTORY_SEPARATOR . $entryName,
                            $sUserTemplateRootDir . DIRECTORY_SEPARATOR . $entryName
                        )
                    ) {
                        $aFailedTemplates[] = $entryName;
                    };
                }
            }
            if (count($aFailedTemplates) > 0) {
                echo "The following templates at {$sStandardTemplateRootDir} could not be moved to the new location at {$sUserTemplateRootDir}:<br /><ul>";
                foreach ($aFailedTemplates as $sFailedTemplate) {
                    echo "<li>{$sFailedTemplate}</li>";
                }
                echo "</ul>Please move these templates manually after the upgrade has finished.<br />";
            }
            // close directory
            closedir($hTemplateDirectory);
    }
}