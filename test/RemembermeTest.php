<?php

ini_set('include_path', dirname(__FILE__).'/../src' . PATH_SEPARATOR . ini_get('include_path'));

require_once 'Rememberme.php';
require_once 'Rememberme/Cookie.php';
require_once 'Rememberme/Storage/Base.php';

class RemembermeTest extends PHPUnit_Framework_TestCase
{
  /**
   * @var Rememberme
   */
  protected $rememberme;
  
  /**
   * Default user id, used as credential information to check
   */
  protected $userid = 1;
  
  protected $validToken = "78b1e6d775cec5260001af137a79dbd5";
  
  protected $validPersistentToken = "0e0530c1430da76495955eb06eb99d95";

  protected $invalidToken = "7ae7c7caa0c7b880cb247bb281d527de";

  protected $cookie;
  
  protected $storage;
  
  function setUp() {
    $this->storage = $this->getMockForAbstractClass("Rememberme_Storage_Base");
    $this->rememberme = new Rememberme($this->storage);
    $this->cookie = $this->getMock("Rememberme_Cookie", array("setcookie"));
    $this->rememberme->setCookie($this->cookie);
    
    $_COOKIE = array();
  }

  /* Basic cases */
  
  public function testReturnFalseIfNoCookieExists()
  {
    $this->assertFalse($this->rememberme->login($this->userid));
  }

  public function testReturnFalseIfCookieIsInvalid()
  {
    $_COOKIE = array($this->rememberme->getCookieName() => "DUMMY");
    $this->assertFalse($this->rememberme->login($this->userid));
    $_COOKIE = array($this->rememberme->getCookieName() => $this->userid."|a");
    $this->assertFalse($this->rememberme->login($this->userid));
  }
  
  public function testLoginTriesToFindTripletWithValuesFromCookie() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->with($this->equalTo($this->userid), $this->equalTo($this->validToken), $this->equalTo($this->validPersistentToken));
    $this->rememberme->login($this->userid);
  }

  /* Success cases */

  public function testReturnTrueIfTripletIsFound() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
      
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->assertTrue($this->rememberme->login($this->userid));
  }

  public function testStoreNewTripletInCookieIfTripletIsFound() {
    $oldcookieValue = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $_COOKIE[$this->rememberme->getCookieName()] = $oldcookieValue;
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with(
        $this->anything(),
        $this->logicalAnd(
          $this->matchesRegularExpression('/^'.$this->userid.'\|[a-f0-9]{32,}\|'.$this->validPersistentToken.'$/'),
          $this->logicalNot($this->equalTo($oldcookieValue))
        )
      );
    $this->rememberme->login($this->userid);
  }

  public function testStoreNewTripletInStorageIfTripletIsFound() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->storage->expects($this->once())
      ->method("storeTriplet")
      ->with(
        $this->equalTo($this->userid),
        $this->logicalAnd(
          $this->matchesRegularExpression('/^[a-f0-9]{32,}$/'),
          $this->logicalNot($this->equalTo($this->validToken))
        ),
        $this->equalTo($this->validPersistentToken)
        );
    $this->rememberme->login($this->userid);
  }

  public function testCookieContainsUserIDAndHexTokensIfTripletIsFound()
  {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with($this->anything(),
          $this->matchesRegularExpression('/^'.$this->userid.'\|[a-f0-9]{32,}\|[a-f0-9]{32,}$/')
        );
    $this->rememberme->login($this->userid);
  }

  public function testCookieContainsNewTokenIfTripletIsFound()
  {
    $oldcookieValue = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $_COOKIE[$this->rememberme->getCookieName()] = $oldcookieValue;
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with($this->anything(),
          $this->logicalAnd(
            $this->matchesRegularExpression('/^'.$this->userid.'\|[a-f0-9]{32,}\|'.$this->validPersistentToken.'$/'),
            $this->logicalNot($this->equalTo($oldcookieValue))
          )
        );
    $this->rememberme->login($this->userid);
  }

  public function testCookieExpiryIsInTheFutureIfTripletIsFound()
  {
    $oldcookieValue = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $_COOKIE[$this->rememberme->getCookieName()] = $oldcookieValue;
    $now = time();
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with($this->anything(), $this->anything(), $this->greaterThan($now));
    $this->rememberme->login($this->userid);
  }

  /* Failure Cases */

  public function testFalseIfTripletIsNotFound() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));

    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_NOT_FOUND));
    $this->assertFalse($this->rememberme->login($this->userid));
  }

  public function testFalseIfTripletIsInvalid() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->invalidToken, $this->validPersistentToken));

    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_INVALID));
    $this->assertFalse($this->rememberme->login($this->userid));
  }

  public function testCookieIsExpiredIfTripletIsInvalid() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->invalidToken, $this->validPersistentToken));
    $now = time();
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_INVALID));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with($this->anything(), $this->anything(), $this->lessThan($now));
    $this->rememberme->login($this->userid);
  }

  public function testAllStoredTokensAreClearedIfTripletIsInvalid() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->invalidToken, $this->validPersistentToken));
    $this->storage->expects($this->any())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_INVALID));
    $this->storage->expects($this->once())
      ->method("cleanAllTriplets")
      ->with($this->equalTo($this->userid));
    $this->rememberme->setCleanStoredTokensOnInvalidResult(true);
    $this->rememberme->login($this->userid);
    $this->rememberme->setCleanStoredTokensOnInvalidResult(false);
    $this->rememberme->login($this->userid);
  }

  public function testInvalidTripletStateIsStored() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->invalidToken, $this->validPersistentToken));

    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_INVALID));
    $this->assertFalse($this->rememberme->loginTokenWasInvalid());
    $this->rememberme->login($this->userid);
    $this->assertTrue($this->rememberme->loginTokenWasInvalid());
  }

  /* Cookie tests */

  public function testCookieNameCanBeSet() {
    $cookieName = "myCustomName";
    $this->rememberme->setCookieName($cookieName);
    $_COOKIE[$cookieName] = implode("|", array($this->userid, $this->validToken, $this->validPersistentToken));
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with($this->equalTo($cookieName));
    $this->assertTrue($this->rememberme->login($this->userid));
  }

  public function testCookieIsSetToConfiguredExpiryDate() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $now = time();
    $expireTime = 31556926; // 1 year
    $this->rememberme->setExpireTime($expireTime);
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->will($this->returnValue(Rememberme_Storage_Base::TRIPLET_FOUND));
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with($this->anything(), $this->anything(), $this->equalTo($now+$expireTime, 10));
    $this->rememberme->login($this->userid);
  }

  /* Salting test */

  public function testOptionalSaltIsAddedToTokens() {
    $salt = "Mozilla Firefox 4.0";
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $this->storage->expects($this->once())
      ->method("findTriplet")
      ->with($this->equalTo($this->userid), $this->equalTo($this->validToken.$salt), $this->equalTo($this->validPersistentToken.$salt));
    $this->rememberme->login($this->userid, $salt);
  }

  /* Other functions */

  public function testCreateCookieCreatesCookieAndStoresTriplets() {
    $now = time();
    $salt = "Mozilla Firefox 4.0";
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with(
        $this->equalTo($this->rememberme->getCookieName()),
        $this->matchesRegularExpression('/^'.$this->userid.'\|[a-f0-9]{32,}\|[a-f0-9]{32,}$/'),
        $this->greaterThan($now)
      );
    $testExpr = '/^[a-f0-9]{32,}'.preg_quote($salt).'$/';
    $this->storage->expects($this->once())
      ->method("storeTriplet")
      ->with(
        $this->equalTo($this->userid),
        $this->matchesRegularExpression($testExpr),
        $this->matchesRegularExpression($testExpr)
      );
    $this->rememberme->createCookie($this->userid, $salt);
  }

  public function testClearCookieExpiresCookieAndDeletesTriplet() {
    $_COOKIE[$this->rememberme->getCookieName()] = implode("|", array(
      $this->userid, $this->validToken, $this->validPersistentToken));
    $now = time();
    $this->cookie->expects($this->once())
      ->method("setcookie")
      ->with(
        $this->equalTo($this->rememberme->getCookieName()),
        $this->anything(),
        $this->lessThan($now)
      );
    $this->storage->expects($this->once())
      ->method("cleanTriplet")
      ->with(
        $this->equalTo($this->userid),
        $this->equalTo($this->validPersistentToken)
      );
    $this->rememberme->clearCookie($this->userid);
  }


  
}