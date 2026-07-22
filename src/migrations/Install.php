<?php

namespace html2img\ogimages\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%ogimages}}')) {
            return true;
        }

        $this->createTable('{{%ogimages}}', [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'url' => $this->string(1024),
            'assetId' => $this->integer(),
            'inputHash' => $this->string(64),
            'width' => $this->integer(),
            'height' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%ogimages}}', ['elementId', 'siteId'], true);
        $this->addForeignKey(null, '{{%ogimages}}', ['elementId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%ogimages}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%ogimages}}', ['assetId'], '{{%assets}}', ['id'], 'SET NULL');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%ogimages}}');

        return true;
    }
}
