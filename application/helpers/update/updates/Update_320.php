            $oDB->createCommand()->update('{{settings_global}}', array('stg_value' => 320), "stg_name='DBVersion'");
            $oTransaction->commit();
