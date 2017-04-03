<?php
/**
 * Php.php
 *
 * The Php class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $Id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Wrapper;

use UserAccessManager\Controller\Controller;

/**
 * Class Php
 *
 * @package UserAccessManager\Wrapper
 */
class Php
{
    /**
     * @see function_exists()
     *
     * @param string $sFunctionName
     *
     * @return bool
     */
    public function functionExists($sFunctionName)
    {
        return function_exists($sFunctionName);
    }

    /**
     * @see openssl_random_pseudo_bytes()
     *
     * @param $iLength
     * @param $blStrong
     *
     * @return string
     */
    public function opensslRandomPseudoBytes($iLength, &$blStrong)
    {
        return openssl_random_pseudo_bytes($iLength, $blStrong);
    }

    /**
     * @param Controller $oController
     * @param string     $sFile
     */
    public function includeFile(Controller &$oController, $sFile)
    {
        /** @noinspection PhpIncludeInspection */
        include $sFile;
    }
}