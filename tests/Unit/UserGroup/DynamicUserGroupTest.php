<?php
/**
 * AbstractUserGroupTest.php
 *
 * The AbstractUserGroupTest unit test class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Tests\Unit\UserGroup;

use UserAccessManager\Object\ObjectHandler;
use UserAccessManager\Tests\Unit\UserAccessManagerTestCase;
use UserAccessManager\UserGroup\DynamicUserGroup;
use UserAccessManager\UserGroup\UserGroupAssignmentException;
use UserAccessManager\UserGroup\UserGroupTypeException;

/**
 * Class DynamicUserGroupTest
 *
 * @package UserAccessManager\Tests\Unit\UserGroup
 * @coversDefaultClass \UserAccessManager\UserGroup\DynamicUserGroup
 */
class DynamicUserGroupTest extends UserAccessManagerTestCase
{
    /**
     * @group  unit
     * @covers ::__construct()
     */
    public function testCanCreateInstance()
    {
        $dynamicUserGroup = new DynamicUserGroup(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getDatabase(),
            $this->getMainConfig(),
            $this->getUtil(),
            $this->getObjectHandler(),
            $this->getAssignmentInformationFactory(),
            DynamicUserGroup::USER_TYPE,
            'id'
        );

        self::assertInstanceOf(DynamicUserGroup::class, $dynamicUserGroup);
        self::assertAttributeEquals('id', 'id', $dynamicUserGroup);
        self::assertAttributeEquals(DynamicUserGroup::USER_TYPE, 'type', $dynamicUserGroup);

        $dynamicUserGroup = new DynamicUserGroup(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getDatabase(),
            $this->getMainConfig(),
            $this->getUtil(),
            $this->getObjectHandler(),
            $this->getAssignmentInformationFactory(),
            DynamicUserGroup::ROLE_TYPE,
            'id'
        );

        self::assertInstanceOf(DynamicUserGroup::class, $dynamicUserGroup);
        self::assertAttributeEquals('id', 'id', $dynamicUserGroup);
        self::assertAttributeEquals(DynamicUserGroup::ROLE_TYPE, 'type', $dynamicUserGroup);

        self::expectException(UserGroupTypeException::class);
        new DynamicUserGroup(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getDatabase(),
            $this->getMainConfig(),
            $this->getUtil(),
            $this->getObjectHandler(),
            $this->getAssignmentInformationFactory(),
            'someThing',
            'id'
        );
    }

    /**
     * @group  unit
     * @covers ::getId()
     */
    public function testGetId()
    {
        $dynamicUserGroup = new DynamicUserGroup(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getDatabase(),
            $this->getMainConfig(),
            $this->getUtil(),
            $this->getObjectHandler(),
            $this->getAssignmentInformationFactory(),
            DynamicUserGroup::ROLE_TYPE,
            'id'
        );

        self::assertEquals(DynamicUserGroup::ROLE_TYPE.'|id', $dynamicUserGroup->getId());
    }

    /**
     * @group  unit
     * @covers ::getName()
     */
    public function testGetName()
    {
        $wordpress = $this->getWordpress();

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $user
         */
        $user = $this->getMockBuilder('\WP_User')
            ->getMock();
        $user->ID = 1;
        $user->display_name = 'displayName';
        $user->user_login = 'userLogin';

        $wordpress->expects($this->once())
            ->method('getUserData')
            ->with(1)
            ->will($this->returnValue($user));

        $roles = new \stdClass();
        $roles->roles = [
            'administrator' => [
                'name' => 'Admin'
            ]
        ];

        $wordpress->expects($this->exactly(2))
            ->method('getRoles')
            ->will($this->returnValue($roles));

        $dynamicUserGroup = new DynamicUserGroup(
            $this->getPhp(),
            $wordpress,
            $this->getDatabase(),
            $this->getMainConfig(),
            $this->getUtil(),
            $this->getObjectHandler(),
            $this->getAssignmentInformationFactory(),
            DynamicUserGroup::USER_TYPE,
            0
        );

        self::assertEquals(TXT_UAM_ADD_DYNAMIC_NOT_LOGGED_IN_USERS, $dynamicUserGroup->getName());

        self::setValue($dynamicUserGroup, 'id', 1);
        self::assertEquals(TXT_UAM_ADD_DYNAMIC_NOT_LOGGED_IN_USERS, $dynamicUserGroup->getName());

        self::setValue($dynamicUserGroup, 'name', null);
        self::assertEquals(TXT_UAM_USER.': displayName (userLogin)', $dynamicUserGroup->getName());

        self::setValue($dynamicUserGroup, 'type', DynamicUserGroup::ROLE_TYPE);
        self::setValue($dynamicUserGroup, 'id', 'roleId');
        self::setValue($dynamicUserGroup, 'name', null);
        self::assertEquals(TXT_UAM_ROLE.': roleId', $dynamicUserGroup->getName());

        self::setValue($dynamicUserGroup, 'id', 'administrator');
        self::setValue($dynamicUserGroup, 'name', null);
        self::assertEquals(TXT_UAM_ROLE.': Admin', $dynamicUserGroup->getName());
    }

    /**
     * @group  unit
     * @covers ::addObject()
     * @throws UserGroupAssignmentException
     * @throws UserGroupAssignmentException
     */
    public function testAddObject()
    {
        $objectHandler = $this->getObjectHandler();
        $objectHandler->expects($this->any())
            ->method('getGeneralObjectType')
            ->will($this->returnCallback(function ($type) {
                return ($type === 'user') ? ObjectHandler::GENERAL_USER_OBJECT_TYPE : 'someType';
            }));

        $dynamicUserGroup = new DynamicUserGroup(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getDatabase(),
            $this->getMainConfig(),
            $this->getUtil(),
            $objectHandler,
            $this->getAssignmentInformationFactory(),
            DynamicUserGroup::USER_TYPE,
            0
        );

        $dynamicUserGroup->addObject('post', 1);

        self::expectException(UserGroupAssignmentException::class);
        $dynamicUserGroup->addObject('user', 1);
    }
}
