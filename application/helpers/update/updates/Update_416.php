
            // encrypt values in db
            SettingGlobal::setSetting(
                'emailsmtppassword',
                LSActiveRecord::encryptSingle(App()->getConfig('emailsmtppassword'))
            );
            SettingGlobal::setSetting(
                'bounceaccountpass',
                LSActiveRecord::encryptSingle(App()->getConfig('bounceaccountpass'))
            );

            // encrypt bounceaccountpass value in db
            alterColumn('{{surveys}}', 'bounceaccountpass', "text", true, 'NULL');
            $sSurveyQuery = "SELECT * from {{surveys}} order by sid";
            $aSurveys = $oDB->createCommand($sSurveyQuery)->queryAll();
            foreach ($aSurveys as $aSurvey) {
                if (!empty($aSurvey['bounceaccountpass'])) {
                    $oDB->createCommand()->update(
                        '{{surveys}}',
                        [
                            'bounceaccountpass' => LSActiveRecord::encryptSingle(
                                $aSurvey['bounceaccountpass']
                            )
                        ],
                        "sid=" . $aSurvey['sid']
                    );
                }
            }

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value' => 416), "stg_name='DBVersion'");
            $oTransaction->commit();
