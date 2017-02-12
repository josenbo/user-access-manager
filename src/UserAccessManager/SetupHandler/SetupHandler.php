<?php
/**
 * SetupHandler.php
 *
 * The SetupHandler class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $Id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\SetupHandler;

use UserAccessManager\Config\Config;
use UserAccessManager\Database\Database;
use UserAccessManager\FileHandler\ApacheFileProtection;
use UserAccessManager\FileHandler\FileHandler;
use UserAccessManager\FileHandler\NginxFileProtection;
use UserAccessManager\ObjectHandler\ObjectHandler;
use UserAccessManager\UserAccessManager;
use UserAccessManager\Wrapper\Wordpress;

/**
 * Class SetupHandler
 * @package UserAccessManager\SetupHandler
 */
class SetupHandler
{
    /**
     * @var Wordpress
     */
    protected $_oWrapper;

    /**
     * @var Database
     */
    protected $_oDatabase;

    /**
     * @var ObjectHandler
     */
    protected $_oObjectHandler;

    /**
     * @var FileHandler
     */
    protected $_oFileHandler;

    /**
     * SetupHandler constructor.
     *
     * @param Wordpress     $oWrapper
     * @param Database      $oDatabase
     * @param ObjectHandler $oObjectHandler
     * @param FileHandler   $oFileHandler
     */
    public function __construct(
        Wordpress $oWrapper,
        Database $oDatabase,
        ObjectHandler $oObjectHandler,
        FileHandler $oFileHandler
    )
    {
        $this->_oWrapper = $oWrapper;
        $this->_oDatabase = $oDatabase;
        $this->_oObjectHandler = $oObjectHandler;
        $this->_oFileHandler = $oFileHandler;
    }

    /**
     * Returns all blog of the network.
     *
     * @return array()
     */
    public function getBlogIds()
    {
        $aBlogIds = array();
        $aSites = $this->_oWrapper->getSites();

        foreach ($aSites as $oSite) {
            $aBlogIds[$oSite->blog_id] = $oSite->blog_id;
        }

        return $aBlogIds;
    }

    /**
     * Installs the user access manager.
     *
     * @param bool $blNetworkWide
     */
    public function install($blNetworkWide = false)
    {
        $aBlogIds = $this->getBlogIds();

        if ($blNetworkWide === true) {
            $iCurrentBlogId = $this->_oDatabase->getCurrentBlogId();

            foreach ($aBlogIds as $iBlogId) {
                $this->_oWrapper->switchToBlog($iBlogId);
                $this->_install();
            }

            $this->_oWrapper->switchToBlog($iCurrentBlogId);
        } else {
            $this->_install();
        }
    }

    /**
     * Creates the needed tables at the database and adds the options
     */
    protected function _install()
    {
        $sCharsetCollate = $this->_oDatabase->getCharset();
        $sDbAccessGroupTable = $this->_oDatabase->getUserGroupTable();

        $sDbUserGroup = $this->_oDatabase->getVariable(
            "SHOW TABLES 
            LIKE '{$sDbAccessGroupTable}'"
        );

        if ($sDbUserGroup !== $sDbAccessGroupTable) {
            $this->_oDatabase->dbDelta(
                "CREATE TABLE {$sDbAccessGroupTable} (
                    ID int(11) NOT NULL auto_increment,
                    groupname tinytext NOT NULL,
                    groupdesc text NOT NULL,
                    read_access tinytext NOT NULL,
                    write_access tinytext NOT NULL,
                    ip_range mediumtext NULL,
                    PRIMARY KEY (ID)
                ) {$sCharsetCollate};"
            );
        }

        $sDbAccessGroupToObjectTable = $this->_oDatabase->getUserGroupToObjectTable();

        $sDbAccessGroupToObject = $this->_oDatabase->getVariable(
            "SHOW TABLES 
            LIKE '".$sDbAccessGroupToObjectTable."'"
        );

        if ($sDbAccessGroupToObject !== $sDbAccessGroupToObjectTable) {
            $this->_oDatabase->dbDelta(
                "CREATE TABLE {$sDbAccessGroupToObjectTable} (
                    object_id VARCHAR(64) NOT NULL,
                    object_type varchar(64) NOT NULL,
                    group_id int(11) NOT NULL,
                    PRIMARY KEY (object_id,object_type,group_id)
                ) {$sCharsetCollate};"
            );
        }

        $this->_oWrapper->addOption('uam_db_version', UserAccessManager::DB_VERSION);
    }

    /**
     * Checks if a database update is necessary.
     *
     * @return boolean
     */
    public function isDatabaseUpdateNecessary()
    {
        $aBlogIds = $this->getBlogIds();

        if (count($aBlogIds) > 0 && $this->_oWrapper->isSuperAdmin()) {
            foreach ($aBlogIds as $iBlogId) {
                $sTable = $this->_oDatabase->getBlogPrefix($iBlogId).'options';
                $sSelect = "SELECT option_value FROM {$sTable} WHERE option_name = %s LIMIT 1";
                $sSelect = $this->_oDatabase->prepare($sSelect, 'uam_db_version');
                $sCurrentDbVersion = $this->_oDatabase->getVariable($sSelect);

                if (version_compare($sCurrentDbVersion, UserAccessManager::DB_VERSION, '<')) {
                    return true;
                }
            }
        }

        $sCurrentDbVersion = $this->_oWrapper->getOption('uam_db_version');
        return version_compare($sCurrentDbVersion, UserAccessManager::DB_VERSION, '<');
    }

    /**
     * Updates the user access manager if an old version was installed.
     *
     * @return true;
     */
    public function update()
    {
        $sCurrentDbVersion = $this->_oWrapper->getOption('uam_db_version');

        if (empty($sCurrentDbVersion)) {
            return false;
        }

        $sUamVersion = $this->_oWrapper->getOption('uam_version');

        if (!$sUamVersion || version_compare($sUamVersion, "1.0", '<')) {
            $this->_oWrapper->deleteOption('allow_comments_locked');
        }

        $sDbAccessGroup = $this->_oDatabase->getUserGroupTable();

        $sDbUserGroup = $this->_oDatabase->getVariable(
            "SHOW TABLES LIKE '{$sDbAccessGroup}'"
        );

        if (version_compare($sCurrentDbVersion, UserAccessManager::DB_VERSION, '<')) {
            $sPrefix = $this->_oDatabase->getPrefix();
            $sCharsetCollate = $this->_oDatabase->getCharset();

            if (version_compare($sCurrentDbVersion, '1.0', '<=')) {
                if ($sDbUserGroup == $sDbAccessGroup) {
                    $sAlterQuery = "ALTER TABLE {$sDbAccessGroup}
                        ADD read_access TINYTEXT NOT NULL DEFAULT '', 
                        ADD write_access TINYTEXT NOT NULL DEFAULT '', 
                        ADD ip_range MEDIUMTEXT NULL DEFAULT ''";
                    $this->_oDatabase->query($sAlterQuery);

                    $sUpdateQuery = "UPDATE {$sDbAccessGroup}
                        SET read_access = 'group', write_access = 'group'";
                    $this->_oDatabase->query($sUpdateQuery);

                    $sSelectQuery = "SHOW columns FROM {$sDbAccessGroup} LIKE 'ip_range'";
                    $sDbIpRange = $this->_oDatabase->getVariable($sSelectQuery);

                    if ($sDbIpRange != 'ip_range') {
                        $sAlterQuery = "ALTER TABLE ".$sDbAccessGroup."
                            ADD ip_range MEDIUMTEXT NULL DEFAULT ''";
                        $this->_oDatabase->query($sAlterQuery);
                    }
                }

                $sDbAccessGroupToObject = $sPrefix.'uam_accessgroup_to_object';
                $sDbAccessGroupToPost = $sPrefix.'uam_accessgroup_to_post';
                $sDbAccessGroupToUser = $sPrefix.'uam_accessgroup_to_user';
                $sDbAccessGroupToCategory = $sPrefix.'uam_accessgroup_to_category';
                $sDbAccessGroupToRole = $sPrefix.'uam_accessgroup_to_role';

                $sAlterQuery = "ALTER TABLE '{$sDbAccessGroupToObject}'
                    CHANGE 'object_id' 'object_id' VARCHAR(64) {$sCharsetCollate}";
                $this->_oDatabase->query($sAlterQuery);

                $aObjectTypes = $this->_oObjectHandler->getObjectTypes();
                $sPostTable = $this->_oDatabase->getPostsTable();

                foreach ($aObjectTypes as $sObjectType) {
                    $sAddition = '';

                    if ($this->_oObjectHandler->isPostableType($sObjectType)) {
                        $sDbIdName = 'post_id';
                        $sDatabase = $sDbAccessGroupToPost.', '.$sPostTable;
                        $sAddition = " WHERE post_id = ID
                            AND post_type = '".$sObjectType."'";
                    } elseif ($sObjectType === 'category') {
                        $sDbIdName = 'category_id';
                        $sDatabase = $sDbAccessGroupToCategory;
                    } elseif ($sObjectType === 'user') {
                        $sDbIdName = 'user_id';
                        $sDatabase = $sDbAccessGroupToUser;
                    } elseif ($sObjectType === 'role') {
                        $sDbIdName = 'role_name';
                        $sDatabase = $sDbAccessGroupToRole;
                    } else {
                        continue;
                    }

                    $sFullDatabase = $sDatabase.$sAddition;

                    $sSql = "SELECT {$sDbIdName} as id, group_id as groupId
                        FROM {$sFullDatabase}";

                    $aDbObjects = (array)$this->_oDatabase->getResults($sSql);

                    foreach ($aDbObjects as $oDbObject) {
                        $this->_oDatabase->insert(
                            $sDbAccessGroupToObject,
                            array(
                                'group_id' => $oDbObject->groupId,
                                'object_id' => $oDbObject->id,
                                'object_type' => $sObjectType,
                            ),
                            array(
                                '%d',
                                '%d',
                                '%s',
                            )
                        );
                    }
                }

                $sDropQuery = "DROP TABLE {$sDbAccessGroupToPost},
                    {$sDbAccessGroupToUser},
                    {$sDbAccessGroupToCategory},
                    {$sDbAccessGroupToRole}";

                $this->_oDatabase->query($sDropQuery);
            }

            $sDbAccessGroupToObject = $this->_oDatabase->getUserGroupToObjectTable();

            if (version_compare($sCurrentDbVersion, '1.2', '<=')) {
                $sSql = "
                    ALTER TABLE `{$sDbAccessGroupToObject}`
                    CHANGE `object_id` `object_id` VARCHAR(64) NOT NULL,
                    CHANGE `object_type` `object_type` VARCHAR(64) NOT NULL";

                $this->_oDatabase->query($sSql);
            }

            if (version_compare($sCurrentDbVersion, '1.3', '<=')) {
                $sTermType = ObjectHandler::TERM_OBJECT_TYPE;
                $this->_oDatabase->update(
                    $sDbAccessGroupToObject,
                    array(
                        'object_type' => $sTermType,
                    ),
                    array(
                        'object_type' => 'category',
                    )
                );
            }

            $this->_oWrapper->updateOption('uam_db_version', UserAccessManager::DB_VERSION);
        }

        return true;
    }

    /**
     * Clean up wordpress if the plugin will be uninstalled.
     */
    public function uninstall()
    {
        $aBlogIds = $this->getBlogIds();

        foreach ($aBlogIds as $iBlogId) {
            $this->_oWrapper->switchToBlog($iBlogId);
            $sUserGroupTable = $this->_oDatabase->getUserGroupTable();
            $sUserGroupToObjectTable = $this->_oDatabase->getUserGroupToObjectTable();

            $sDropQuery = "DROP TABLE {$sUserGroupTable}, {$sUserGroupToObjectTable}";
            $this->_oDatabase->query($sDropQuery);

            $this->_oWrapper->deleteOption(Config::ADMIN_OPTIONS_NAME);
            $this->_oWrapper->deleteOption('uam_version');
            $this->_oWrapper->deleteOption('uam_db_version');
        }

        $this->_oFileHandler->deleteFileProtection();
    }

    /**
     * Remove the htaccess file if the plugin is deactivated.
     *
     * @return bool
     */
    public function deactivate()
    {
        return $this->_oFileHandler->deleteFileProtection();
    }
}