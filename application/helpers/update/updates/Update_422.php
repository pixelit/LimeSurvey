            //update core themes api_version
            $oDB->createCommand()->update(
                '{{templates}}',
                array(
                    'api_version' => "4.0",
                    'version' => "4.0",
                    'copyright' => "Copyright (C) 2007-2019 The LimeSurvey Project Team\r\nAll rights reserved."
                ),
                "name='fruity'"
            );
            $oDB->createCommand()->update(
                '{{templates}}',
                array(
                    'api_version' => "4.0",
                    'version' => "4.0",
                    'copyright' => "Copyright (C) 2007-2019 The LimeSurvey Project Team\r\nAll rights reserved."
                ),
                "name='vanilla'"
            );
            $oDB->createCommand()->update(
                '{{templates}}',
                array(
                    'api_version' => "4.0",
                    'version' => "4.0",
                    'copyright' => "Copyright (C) 2007-2019 The LimeSurvey Project Team\r\nAll rights reserved."
                ),
                "name='bootwatch'"
            );
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value' => 422), "stg_name='DBVersion'");
            $oTransaction->commit();
