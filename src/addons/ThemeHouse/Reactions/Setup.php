<?php

namespace ThemeHouse\Reactions;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * Install Functions
     */
    public function installStep1()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $schemaManager->createTable('xf_th_reaction', function(\XF\Db\Schema\Create $table) {
            $table->addColumn('reaction_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar', 50);
            $table->addColumn('reaction_type_id', 'varchar', 25);
            $table->addColumn('styling_type', 'varchar', 25);
            $table->addColumn('reaction_text', 'varchar', 10);
            $table->addColumn('image_url', 'varchar', 250);
            $table->addColumn('image_url_2x', 'varchar', 250);
            $table->addColumn('image_type', 'varchar', 25);
            $table->addColumn('styling', 'text');
            $table->addColumn('user_criteria', 'mediumblob')->nullable();
            $table->addColumn('react_handler', 'varbinary', 255);
            $table->addColumn('options', 'text');
            $table->addColumn('display_order', 'int')->setDefault(1);
            $table->addColumn('like_wrapper', 'bool')->setDefault(0);
            $table->addColumn('random', 'bool')->setDefault(0);
            $table->addColumn('enabled', 'bool')->setDefault(1);
        });

        $schemaManager->createTable('xf_th_reaction_type', function(\XF\Db\Schema\Create $table) {
            $table->addColumn('reaction_type_id', 'varchar', 25);
            $table->addColumn('title', 'varchar', 50);
            $table->addColumn('color', 'varchar', 25);
            $table->addColumn('display_order', 'int')->setDefault(1);
            $table->addPrimaryKey('reaction_type_id');
        });

        $schemaManager->createTable('xf_th_reacted_content', function(\XF\Db\Schema\Create $table) {
            $table->addColumn('react_id', 'int')->autoIncrement();
            $table->addColumn('reaction_id', 'int');
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('content_id', 'int');
            $table->addColumn('react_user_id', 'int');
            $table->addColumn('react_date', 'int');
            $table->addColumn('content_user_id', 'int');
            $table->addColumn('is_counted', 'bool')->setDefault(1);
            $table->addKey(['content_type', 'content_id', 'react_user_id'], 'content_type_id_react_user_id');
            $table->addKey(['react_user_id', 'content_type', 'content_id'], 'react_user_content_type_id');
            $table->addKey(['content_user_id', 'react_date'], 'content_user_id_react_date');
            $table->addKey('react_date', 'react_date');
        });

        $schemaManager->createTable('xf_th_reaction_user_count', function(\XF\Db\Schema\Create $table) {
            $table->addColumn('user_id', 'int');
            $table->addColumn('reaction_id', 'int');
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('count_received', 'int')->setDefault(0);
            $table->addColumn('count_given', 'int')->setDefault(0);
            $table->addUniqueKey(['user_id', 'reaction_id'], 'user_reaction_id');
            $table->addUniqueKey(['content_type', 'user_id', 'reaction_id'], 'content_type_user_reaction_id');
        });

        $schemaManager->createTable('xf_th_reaction_content_count', function(\XF\Db\Schema\Create $table) {
            $table->addColumn('content_id', 'int');
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('reaction_id', 'int');
            $table->addColumn('count', 'int')->setDefault(0);
            $table->addUniqueKey(['content_type', 'content_id'], 'content_type_id');
        });
    }

    public function installStep2()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $this->applyGlobalPermission('thReactions', 'canReact');
        $this->applyGlobalPermission('thReactions', 'canRemoveOwnReacts');
        $this->applyGlobalPermission('thReactions', 'canViewReactsList');

        $this->applyGlobalPermissionInt('thReactions', 'maxReactsPerContent', '1');
        $this->applyGlobalPermissionInt('thReactions', 'maxReactsPerContent', '1');
        $this->applyGlobalPermissionInt('thReactions', 'dailyReactLimit', '-1');

        $schemaManager->alterTable('xf_thread', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('first_react_users', 'blob')->nullable();
        });

        $schemaManager->alterTable('xf_conversation_master', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('first_react_users', 'blob')->nullable();
        });

        $schemaManager->alterTable('xf_user', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('react_count', 'blob')->nullable();
        });
    }

    public function installStep3()
    {
        $schemaManager = $this->db()->getSchemaManager();
        $schemaManager->alterTable('xf_post', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('react_users', 'blob')->nullable();
        });
    }

    public function installStep4()
    {
        $schemaManager = $this->db()->getSchemaManager();
        $schemaManager->alterTable('xf_conversation_message', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('react_users', 'blob')->nullable();
        });
    }

    public function installStep5()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $schemaManager->alterTable('xf_profile_post', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('react_users', 'blob')->nullable();
        });
        $schemaManager->alterTable('xf_profile_post_comment', function(\XF\Db\Schema\Alter $table) {
            $table->addColumn('react_users', 'blob')->nullable();
        });
    }

    public function postInstall(array &$stateChanges)
    {
        /** @var \ThemeHouse\Reactions\Service\Import $reactionImporter */
        $reactionImporter = \XF::service('ThemeHouse\Reactions:Import');

        $xml = \XF\Util\Xml::openFile(\XF::getSourceDirectory() . '/addons/ThemeHouse/Reactions/reactions.xml');
        if ($xml) {
            $reactionTypes = $reactionImporter->getReactionTypeFromXml($xml);
            $reactionImporter->importReactionTypes($reactionTypes);

            $reactions = $reactionImporter->getReactionFromXml($xml);
            $reactionImporter->importReactions($reactions);
        }
    }

    public function upgrade1000294Step1()
    {
        $schemaManager = $this->schemaManager();

        $schemaManager->alterTable('xf_th_reaction', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('user_criteria', 'mediumblob')->nullable();
        });
    }

    public function upgrade1000294Step2()
    {
        $schemaManager = $this->schemaManager();

        $schemaManager->alterTable('xf_thread', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('first_react_users', 'blob')->nullable();
        });

        $schemaManager->alterTable('xf_conversation_master', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('first_react_users', 'blob')->nullable();
        });

        $schemaManager->alterTable('xf_user', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('react_count', 'blob')->nullable();
        });
    }

    public function upgrade1000294Step3()
    {
        $schemaManager = $this->schemaManager();

        $schemaManager->alterTable('xf_post', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('react_users', 'blob')->nullable();
        });
    }

    public function upgrade1000294Step4()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $schemaManager->alterTable('xf_conversation_message', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('react_users', 'blob')->nullable();
        });
    }

    public function upgrade1000294Step5()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $schemaManager->alterTable('xf_profile_post', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('react_users', 'blob')->nullable();
        });
        $schemaManager->alterTable('xf_profile_post_comment', function(\XF\Db\Schema\Alter $table) {
            $table->changeColumn('react_users', 'blob')->nullable();
        });
    }

    public function upgrade1000470Step1()
    {
        $schemaManager = $this->schemaManager();

        $schemaManager->renameTable('xf_th_reaction_count', 'xf_th_reaction_user_count');

        $schemaManager->createTable('xf_th_reaction_content_count', function(\XF\Db\Schema\Create $table) {
            $table->addColumn('content_id', 'int');
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('reaction_id', 'int');
            $table->addColumn('count', 'int')->setDefault(0);
            $table->addUniqueKey(['content_type', 'content_id'], 'content_type_id');
        });
    }

    /**
     * Uninstall Functions
     */
    public function uninstallStep1()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $schemaManager->dropTable('xf_th_reaction');
        $schemaManager->dropTable('xf_th_reaction_type');
        $schemaManager->dropTable('xf_th_reacted_content');
        $schemaManager->dropTable('xf_th_reaction_user_count');
    }

    public function uninstallStep2()
    {
        $schemaManager = $this->db()->getSchemaManager();

        $schemaManager->alterTable('xf_thread', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['first_react_users']);
        });
        $schemaManager->alterTable('xf_post', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['react_users']);
        });

        $schemaManager->alterTable('xf_conversation_message', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['react_users']);
        });
        $schemaManager->alterTable('xf_conversation_master', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['react_users']);
        });

        $schemaManager->alterTable('xf_profile_post', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['react_users']);
        });
        $schemaManager->alterTable('xf_profile_post_comment', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['react_users']);
        });

        $schemaManager->alterTable('xf_user', function(\XF\Db\Schema\Alter $table) {
            $table->dropColumns(['react_count']);
        });
    }

    public function uninstallStep3()
    {
        \XF::registry()->delete(['reactions', 'reactionTypes']);
    }
}