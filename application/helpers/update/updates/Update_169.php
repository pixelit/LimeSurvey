            // Add new column for question index options.
            addColumn('{{surveys}}', 'questionindex', 'integer not null default 0');
            // Set values for existing surveys.
            $oDB->createCommand("update {{surveys}} set questionindex = 0 where allowjumps <> 'Y'")->query();
            $oDB->createCommand("update {{surveys}} set questionindex = 1 where allowjumps = 'Y'")->query();

            // Remove old column.
            dropColumn('{{surveys}}', 'allowjumps');
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value' => 169), "stg_name='DBVersion'");
            $oTransaction->commit();
