

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value' => 314), "stg_name='DBVersion'");
            $oTransaction->commit();
