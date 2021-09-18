<?php

namespace yii2swoole\webserver\session;

use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;
use yii\web\SessionIterator;

/**
 * Class Session
 * @package yii2swoole\webserver\session
 */
class Session extends \yii\web\Session
{

    /**
     * @var string session name
     */
    public $name = 'PHPSESSID';

    /**
     * @var string session name
     */
    public $savePath = '@runtime/session';

    /**
     * @var string session id
     */
    protected $_sessionId = null;

    /**
     * @var array
     */
    protected $sessionData = [];

    /**
     * @var int
     */
    protected $_sessionStatus = PHP_SESSION_DISABLED;

    /**
     * @var array
     */
    private $_cookieParams = ['httponly' => true];

    /**
     * Initializes the application component.
     * This method is required by IApplicationComponent and is invoked by application.
     */
    public function init()
    {
        $this->savePath = Yii::getAlias($this->savePath);
        FileHelper::createDirectory($this->savePath);
        if ($this->getIsActive()) {
            Yii::warning('Session is already started', __METHOD__);
            $this->updateFlashCounters();
        }
    }

    /**
     * returns a value indicating whether to use custom session storage.
     * This method should be overridden to return true by child classes that implement custom session storage.
     * To implement custom session storage, override these methods: [[openSession()]], [[closeSession()]],
     * [[readSession()]], [[writeSession()]], [[destroySession()]] and [[gcSession()]].
     * @return bool whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return true;
    }

    /**
     * Starts the session.
     */
    public function open()
    {
        if ($this->getIsActive()) {
            return;
        }

        $this->setCookieParamsInternal();

        $this->sessionStart();

        if ($this->getIsActive()) {
            Yii::info('Session started', __METHOD__);
            $this->updateFlashCounters();
            $this->setCookieSessionId();
        } else {
            $error = error_get_last();
            $message = isset($error['message']) ? $error['message'] : 'Failed to start session.';
            Yii::error($message, __METHOD__);
        }
    }

    /**
     * sessionStart
     */
    public function sessionStart()
    {
        $cookie = Yii::$app->request->getSwooleRequest()->cookie;
        $sid = $cookie[$this->getName()] ?? null;
        if ($sid) {
            $this->setId($sid);
            $this->getSessionData();
        } else {
            $this->regenerateID();
        }

        $this->_sessionStatus = PHP_SESSION_ACTIVE;
    }

    /**
     * setCookieSessionId
     */
    private function setCookieSessionId()
    {
        if ($this->getHasSessionId() === false) {
            $data = $this->getCookieParams();
            $response = Yii::$app->getResponse();
            /** @var yii\web\Cookie $cookie */
            if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
                $expire = $data['lifetime'] ? time() + $data['lifetime'] : 0;
                $response->getSwooleResponse()->cookie($this->getName(), $this->getId(), $expire, $data['path'], $data['domain'], $data['secure'], $data['httponly']);
            } else {
                $response->getSwooleResponse()->cookie($this->getName(), $this->getId());
            }
        }
    }

    /**
     * Ends the current session and store session data.
     */
    public function close()
    {
        if ($this->getIsActive()) {
            $this->_sessionStatus = PHP_SESSION_DISABLED;
        }
        $this->closeSession();
        $this->sessionData = [];
        $this->setId(null);
    }

    /**
     * Frees all session variables and destroys all data registered to a session.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     * @see open()
     * @see isActive
     */
    public function destroy()
    {
        if ($this->getIsActive()) {
            $this->close();
            $this->destroySession($this->getId());
            $this->open();
        }
    }

    /**
     * @return bool whether the session has started
     */
    public function getIsActive()
    {
        return $this->_sessionStatus === PHP_SESSION_ACTIVE;
    }

    private $_hasSessionId;

    /**
     * Returns a value indicating whether the current request has sent the session ID.
     * The default implementation will check cookie and $_GET using the session name.
     * If you send session ID via other ways, you may need to override this method
     * or call [[setHasSessionId()]] to explicitly set whether the session ID is sent.
     * @return bool whether the current request has sent the session ID.
     */
    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            $name = $this->getName();
            $request = Yii::$app->getRequest();
            if (!empty($request->cookies->get($name)) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_hasSessionId = $request->get($name) != '';
            } else {
                $this->_hasSessionId = false;
            }
        }

        return $this->_hasSessionId;
    }

    /**
     * Sets the value indicating whether the current request has sent the session ID.
     * This method is provided so that you can override the default way of determining
     * whether the session ID is sent.
     * @param bool $value whether the current request has sent the session ID.
     */
    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    /**
     * Gets the session ID.
     * This is a wrapper for [PHP session_id()](https://secure.php.net/manual/en/function.session-id.php).
     * @return string the current session ID
     */
    public function getId()
    {
        return $this->_sessionId;
    }

    /**
     * Sets the session ID.
     * This is a wrapper for [PHP session_id()](https://secure.php.net/manual/en/function.session-id.php).
     * @param string $value the session ID for the current session
     */
    public function setId($value)
    {
        $this->_sessionId = $value;
    }

    /**
     * Updates the current session ID with a newly generated one.
     *
     * Please refer to <https://secure.php.net/session_regenerate_id> for more details.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     * @see open()
     * @see isActive
     */
    public function regenerateID($deleteOldSession = false)
    {
        $this->setId(md5(uniqid().Yii::$app->request->getUserIP()));
        $this->_hasSessionId = false;
        $this->setCookieSessionId();
        $this->_hasSessionId = true;
    }

    /**
     * Gets the name of the current session.
     * This is a wrapper for [PHP session_name()](https://secure.php.net/manual/en/function.session-name.php).
     * @return string the current session name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name for the current session.
     * This is a wrapper for [PHP session_name()](https://secure.php.net/manual/en/function.session-name.php).
     * @param string $value the session name for the current session, must be an alphanumeric string.
     * It defaults to "PHPSESSID".
     */
    public function setName($value)
    {
        $this->name = $value;
    }

    /**
     * Gets the current session save path.
     * This is a wrapper for [PHP session_save_path()](https://secure.php.net/manual/en/function.session-save-path.php).
     * @return string the current session save path, defaults to '/tmp'.
     */
    public function getSavePath()
    {
        return $this->savePath;
    }

    /**
     * Sets the current session save path.
     * This is a wrapper for [PHP session_save_path()](https://secure.php.net/manual/en/function.session-save-path.php).
     * @param string $value the current session save path. This can be either a directory name or a [path alias](guide:concept-aliases).
     * @throws InvalidArgumentException if the path is not a valid directory
     */
    public function setSavePath($value)
    {
        $path = Yii::getAlias($value);
        if (is_dir($path) && is_writable($path)) {
            $this->savePath = $path;
        } else {
            throw new InvalidArgumentException("Session save path is must be writable directory: $value");
        }
    }

    /**
     * Sets the session cookie parameters.
     * This method is called by [[open()]] when it is about to open the session.
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see https://secure.php.net/manual/en/function.session-set-cookie-params.php
     */
    private function setCookieParamsInternal()
    {
        $this->_cookieParams  = array_merge([
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'httpOnly' => true,
        ],$this->_cookieParams);
    }

    /**
     * @var bool Whether strict mode is enabled or not.
     * When `true` this setting prevents the session component to use an uninitialized session ID.
     * Note: Enabling `useStrictMode` on PHP < 5.5.2 is only supported with custom storage classes.
     * Warning! Although enabling strict mode is mandatory for secure sessions, the default value of 'session.use-strict-mode' is `0`.
     * @see https://www.php.net/manual/en/session.configuration.php#ini.session.use-strict-mode
     * @since 2.0.38
     */
    public function setUseStrictMode($value)
    {
        $this->freeze();
        ini_set('session.use_strict_mode', $value ? '1' : '0');
        $this->unfreeze();
    }

    /**
     * @return bool Whether strict mode is enabled or not.
     * @see setUseStrictMode()
     * @since 2.0.38
     */
    public function getUseStrictMode()
    {
        return true;
    }

    /**
     * getSessionFileName
     * @return string
     */
    protected function getSessionFileName()
    {
        return $this->savePath.DIRECTORY_SEPARATOR.$this->getId();
    }

    /**
     * getSessionData
     * @return array|mixed
     */
    protected function getSessionData()
    {
        if(empty($this->sessionData)){
            $file = $this->getSessionFileName();
            if(is_file($file)){
                $this->sessionData = unserialize(file_get_contents($file)?:'')?:[];
            }
        }
        return $this->sessionData;
    }

    /**
     * setSessionData
     * @param array $value
     * @return bool
     */
    protected function setSessionData($value=[])
    {
        $this->sessionData = $value;
        return true;
    }

    /**
     * Session open handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Session close handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @return bool whether session is closed successfully
     */
    public function closeSession()
    {
        return file_put_contents($this->getSessionFileName(),serialize($this->sessionData??[]));
    }

    /**
     * readSession
     * @param string $id
     * @return mixed|string|null
     */
    public function readSession($id)
    {
        return $this->getSessionData()[$id]??null;
    }

    /**
     * writeSession
     * @param string $id
     * @param mixed $data
     * @return bool|int
     */
    public function writeSession($id, $data)
    {
        $value = $this->getSessionData();
        $value[$id] = $data;
        return $this->setSessionData($value);
    }

    /**
     * Session destroy handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $value = $this->getSessionData();
        if(isset($value[$id])){
            unset($value[$id]);
            return $this->setSessionData($value);
        }
        return true;
    }

    /**
     * Session GC (garbage collection) handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return bool whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        $nowTime = time();
        if($maxLifetime){
            try {
                $sessionFile = FileHelper::findFiles($this->savePath);
                foreach ($sessionFile as $file){
                    if( fileatime($file) + $maxLifetime < $nowTime){
                        unlink($file);
                    }
                }
            }catch (\Exception $exception){}
        }
        return true;
    }

    /**
     * Returns an iterator for traversing the session variables.
     * This method is required by the interface [[\IteratorAggregate]].
     * @return SessionIterator an iterator for traversing the session variables.
     */
    public function getIterator()
    {
        $this->open();
        return new SessionIterator();
    }

    /**
     * Returns the number of items in the session.
     * @return int the number of session variables
     */
    public function getCount()
    {
        $this->open();
        return count($this->getSessionData());
    }

    /**
     * Returns the number of items in the session.
     * This method is required by [[\Countable]] interface.
     * @return int number of items in the session.
     */
    public function count()
    {
        return $this->getCount();
    }

    /**
     * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     */
    public function get($key, $defaultValue = null)
    {
        $this->open();
        $value = $this->readSession($key);
        return !empty($value) ? $value : $defaultValue;
    }

    /**
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed $value session variable value
     */
    public function set($key, $value)
    {
        $this->open();
        $this->writeSession($key,$value);
    }

    /**
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     * @return mixed the removed value, null if no such session variable.
     */
    public function remove($key)
    {
        $this->open();

        return $this->destroySession($key);
    }

    /**
     * Removes all session variables.
     */
    public function removeAll()
    {
        $this->open();
        $this->setSessionData();
    }

    /**
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     */
    public function has($key)
    {
        $this->open();
        return isset($this->getSessionData()[$key]);
    }

    /**
     * Updates the counters for flash messages and removes outdated flash messages.
     * This method should only be called once in [[init()]].
     */
    protected function updateFlashCounters()
    {
        $counters = $this->get($this->flashParam, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key]);
                    $this->destroySession($key);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $this->writeSession($this->flashParam,$counters);
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            $this->destroySession($this->flashParam);
        }
    }

    /**
     * Returns a flash message.
     * @param string $key the key identifying the flash message
     * @param mixed $defaultValue value to be returned if the flash message does not exist.
     * @param bool $delete whether to delete this flash message right after this method is called.
     * If false, the flash message will be automatically deleted in the next request.
     * @return mixed the flash message or an array of messages if addFlash was used
     * @see setFlash()
     * @see addFlash()
     * @see hasFlash()
     * @see getAllFlashes()
     * @see removeFlash()
     */
    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                $counters[$key] = 1;
                $this->writeSession($this->flashParam,$counters);
            }

            return $value;
        }

        return $defaultValue;
    }

    /**
     * Returns all flash messages.
     *
     * You may use this method to display all the flash messages in a view file:
     *
     * ```php
     * <?php
     * foreach (Yii::$app->session->getAllFlashes() as $key => $message) {
     *     echo '<div class="alert alert-' . $key . '">' . $message . '</div>';
     * } ?>
     * ```
     *
     * With the above code you can use the [bootstrap alert][] classes such as `success`, `info`, `danger`
     * as the flash message key to influence the color of the div.
     *
     * Note that if you use [[addFlash()]], `$message` will be an array, and you will have to adjust the above code.
     *
     * [bootstrap alert]: http://getbootstrap.com/components/#alerts
     *
     * @param bool $delete whether to delete the flash messages right after this method is called.
     * If false, the flash messages will be automatically deleted in the next request.
     * @return array flash messages (key => message or key => [message1, message2]).
     * @see setFlash()
     * @see addFlash()
     * @see getFlash()
     * @see hasFlash()
     * @see removeFlash()
     */
    public function getAllFlashes($delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        $flashes = [];
        $sessionData = $this->getSessionData();
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $sessionData)) {
                $flashes[$key] = $sessionData[$key];
                if ($delete) {
                    unset($counters[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }

        $this->writeSession($this->flashParam,$counters);

        return $flashes;
    }

    /**
     * Sets a flash message.
     * A flash message will be automatically deleted after it is accessed in a request and the deletion will happen
     * in the next request.
     * If there is already an existing flash message with the same key, it will be overwritten by the new one.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space. If you have a normal
     * session variable using the same name, its value will be overwritten by this method.
     * @param mixed $value flash message
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->writeSession($key,$value);
        $this->writeSession($this->flashParam,$counters);
    }

    /**
     * Adds a flash message.
     * If there are existing flash messages with the same key, the new one will be appended to the existing message array.
     * @param string $key the key identifying the flash message.
     * @param mixed $value flash message
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see setFlash()
     * @see removeFlash()
     */
    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->writeSession($this->flashParam,$counters);
        $sessionData = $this->getSessionData();
        if (empty($sessionData[$key])) {
            $this->writeSession($this->flashParam,$counters);
        } elseif (is_array($sessionData[$key])) {
            $updateValue = $sessionData[$key];
            $updateValue[] = $value;
            $this->writeSession($key,$updateValue);
        } else {
            $updateValue = [$sessionData[$key],$value];
            $this->writeSession($key,$updateValue);
        }
    }

    /**
     * Removes a flash message.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space.  If you have a normal
     * session variable using the same name, it will be removed by this method.
     * @return mixed the removed flash message. Null if the flash message does not exist.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeAllFlashes()
     */
    public function removeFlash($key)
    {
        $counters = $this->get($this->flashParam, []);

        $value = $this->readSession($key);

        $value = isset($counters[$key]) && !empty($value) ? $value : null;

        unset($counters[$key]);

        $this->destroySession($key);

        $this->writeSession($this->flashParam,$counters);

        return $value;
    }

    /**
     * Removes all flash messages.
     * Note that flash messages and normal session variables share the same name space.
     * If you have a normal session variable using the same name, it will be removed
     * by this method.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function removeAllFlashes()
    {
        $counters = $this->get($this->flashParam, []);
        foreach (array_keys($counters) as $key) {
            $this->destroySession($key);
        }
        $this->destroySession($this->flashParam);
    }

    /**
     * Returns a value indicating whether there are flash messages associated with the specified key.
     * @param string $key key identifying the flash message type
     * @return bool whether any flash messages exist under specified key
     */
    public function hasFlash($key)
    {
        return $this->getFlash($key) !== null;
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to check on
     * @return bool
     */
    public function offsetExists($offset)
    {
        $this->open();

        return isset($_SESSION[$offset]);
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        $this->open();

        $value = $this->readSession($offset);

        return !empty($value) ? $value : null;
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to set element
     * @param mixed $item the element value
     */
    public function offsetSet($offset, $item)
    {
        $this->open();
        $this->writeSession($offset,$item);
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset)
    {
        $this->open();
        $this->destroySession($offset);
    }

    /**
     * If session is started it's not possible to edit session ini settings. In PHP7.2+ it throws exception.
     * This function saves session data to temporary variable and stop session.
     * @since 2.0.14
     */
    protected function freeze()
    {
        return;
    }

    /**
     * Starts session and restores data from temporary variable
     * @since 2.0.14
     */
    protected function unfreeze()
    {
        return;
    }

    /**
     * Set cache limiter
     *
     * @param string $cacheLimiter
     * @since 2.0.14
     */
    public function setCacheLimiter($cacheLimiter)
    {
        $this->freeze();
        session_cache_limiter($cacheLimiter);
        $this->unfreeze();
    }
}