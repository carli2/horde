<?php
/**
 * Horde Mnemo driver for the Kolab_Storage backend.
 *
 * Copyright 2004-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Gunnar Wrobel <wrobel@pardus.de>
 * @author  Thomas Jarosch <thomas.jarosch@intra2net.com>
 * @author  Stuart Binge <omicron@mighty.co.za>
 * @since   Mnemo 2.0
 * @package Mnemo
 */
class Mnemo_Driver_Kolab extends Mnemo_Driver
{
    /**
     * The Kolab_Storage backend.
     *
     * @var Horde_Kolab_Storage
     */
    private $_kolab;

    /**
     * The specific notepad.
     *
     * @var Horde_Kolab_Storage_Data
     */
    private $_data;

    /**
     * Construct a new Kolab storage object.
     *
     * @param string $notepad  The name of the notepad to load/save notes from.
     * @param array $params    The connection parameters
     *
     * @throws InvalidArguementException
     */
    public function __construct($notepad, $params = array())
    {
        if (empty($params['storage'])) {
            throw new InvalidArgumentException('Missing required storage handler.');
        }
        $this->_notepad = $notepad;
        $this->_kolab = $params['storage'];
        $this->_data = $this->_kolab->getData(
            $GLOBALS['mnemo_shares']->getShare($this->_notepad)->get('folder'),
            'note'
        );
    }

    /**
     * Retrieve one note from the store.
     *
     * @param string $noteId      The ID of the note to retrieve.
     * @param string $passphrase  A passphrase with which this note was
     *                            supposed to be encrypted.
     *
     * @return array  The array of note attributes.
     */
    function get($noteId, $passphrase = null)
    {
        if ($this->_data->objectIdExists($noteId)) {
            $note = $this->_object->getObject($noteId);
            return $this->_buildNote($note, $passphrase);
        } else {
            throw new Horde_Exception_NotFound(_("Not Found"));
        }
    }

    /**
     * Retrieve one note by UID.
     *
     * @param string $uid         The UID of the note to retrieve.
     * @param string $passphrase  A passphrase with which this note was
     *                            supposed to be encrypted.
     *
     * @return array  The array of note attributes.
     */
    function getByUID($uid, $passphrase = null)
    {
        return $this->_wrapper->getByUID($uid, $passphrase);
    }

    /**
     * Add a note to the backend storage.
     *
     * @param string $desc        The first line of the note.
     * @param string $body        The whole note body.
     * @param string $category    The category of the note.
     * @param string $uid         A Unique Identifier for the note.
     * @param string $passphrase  The passphrase to encrypt the note with.
     *
     * @return string  The unique ID of the new note.
     * @throws Mnemo_Exception
     */
    public function add($desc, $body, $category = '', $uid = null, $passphrase = null)
    {
        if (is_null($uid)) {
            $uid = $this->_data->generateUid();
        }

        if ($passphrase) {
            $body = $this->_encrypt($body, $passphrase);
            Mnemo::storePassphrase($uid, $passphrase);
        }

        $this->_data->create(
            array(
                'uid' => $uid,
                'desc' => $desc,
                'body' => $body,
                'categories' => $category,
            )
        );

        // Log the creation of this item in the history log.
        // @TODO: Inject the history driver
        $history = $GLOBALS['injector']->getInstance('Horde_History');
        $history->log('mnemo:' . $this->_notepad . ':' . $uid, array('action' => 'add'), true);

        return $uid;
    }

    /**
     * Add or modify a note.
     *
     * @param string $desc        The description (long) of the note.
     * @param string $body        The description (long) of the note.
     * @param string $category    The category of the note.
     * @param string $uid         The note to modify.
     * @param string $passphrase  The passphrase to encrypt the note with.
     *
     * @return mixed The id of the note if successful, a PEAR error
     * otherwise
     */
    function _setObject($desc, $body, $category = '', $uid = null, $passphrase = null)
    {
        if (empty($uid)) {
            $note_uid = strval(new Horde_Support_Uuid());
            $old_uid = null;
            $action = array('action' => 'add');
        } else {
            list($note_uid, $notepad) = $this->_splitId($uid);
            $old_uid = $note_uid;
            $action = array('action' => 'modify');
        }

        if ($passphrase) {
            $body = $this->_encrypt($body, $passphrase);
            Mnemo::storePassphrase($note_uid, $passphrase);
        }

        $result = $this->_store->save(array('uid' => $note_uid,
                                            'desc' => $desc,
                                            'body' => $body,
                                            'categories' => $category,
                                            ),
                                      $old_uid);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        /* Log the action in the history log. */
        $history = $GLOBALS['injector']->getInstance('Horde_History');
        $history->log('mnemo:' . $this->_notepad . ':' . $this->_uniqueId($note_uid), $action, true);

        return $this->_uniqueId($note_uid);
    }

    /**
     * Modify an existing note.
     *
     * @param integer $noteId   The note to modify.
     * @param string $desc      The description (long) of the note.
     * @param string $body      The description (long) of the note.
     * @param string $category  The category of the note.
     * @param string $passphrase  The passphrase to encrypt the note with.
     *
     * @return booelan
     */
    function modify($noteId, $desc, $body, $category = '', $passphrase = null)
    {
        try {
            $this->_wrapper->modify($noteId, $desc, $body, $category, $passphrase);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Move a note to a new notepad.
     *
     * @param string $noteId      The note to move.
     * @param string $newNotepad  The new notepad.
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function move($noteId, $newNotepad)
    {
        return $this->_wrapper->move($noteId, $newNotepad);
    }

    /**
     * Delete the specified note from the current notepad
     *
     * @param string $noteId      The note to delete.
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function delete($noteId)
    {
        return $this->_wrapper->delete($noteId);
    }

    /**
     * Delete all notes from the current notepad
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function deleteAll()
    {
        return $this->_wrapper->deleteAll();
    }

    /**
     * Retrieves all of the notes from $this->_notepad from the database.
     *
     * @return NULL
     *
     * @throws Mnemo_Exception
     */
    function retrieve()
    {
        $this->_memos = array();

        $note_list = $this->_store->getObjects();
        if (empty($note_list)) {
            return;
        }

        foreach ($note_list as $note) {
            $nuid = $this->_uniqueId($note['uid']);
            $this->_memos[$nuid] = $this->_buildNote($note);
        }
    }

    /**
     * Append the notepad name to the id. We use this to make ids
     * unique across folders.
     *
     * @param string $id The ID of the note
     *
     * @return string  The note id appended with the notepad
     *                 name.
     */
    function _uniqueId($id)
    {
        return $id . '@' . $this->_notepad;
    }

    /**
     * Build a note based on data array
     *
     * @param array  $note     The data for the note
     * @param string $passphrase A passphrase for decrypting a note
     *
     * @return array  The converted data array representing the note
     */
    function _buildNote($note, $passphrase = null)
    {
        $note['memolist_id'] = $this->_notepad;
        $note['memo_id'] = $note['uid'];

        $note['category'] = $note['categories'];
        unset($note['categories']);

        $note['encrypted'] = false;
        $body = $note['body'];

        if (strpos($body, '-----BEGIN PGP MESSAGE-----') === 0) {
            $note['encrypted'] = true;
            if (empty($passphrase)) {
                $passphrase = Mnemo::getPassphrase($note['memo_id']);
            }
            if (empty($passphrase)) {
                $body = new Mnemo_Exception(_("This note has been encrypted."), Mnemo::ERR_NO_PASSPHRASE);
            } else {
                try {
                    $body = $this->_decrypt($body, $passphrase);
                    $body = $body->message;
                } catch (Mnemo_Exception $e) {
                    $body = $e;
                }
                Mnemo::storePassphrase($row['memo_id'], $passphrase);
            }
        }
        $note['body'] = $body;

        return $note;
    }
}

/* /\** */
/*  * Horde Mnemo wrapper to distinguish between both Kolab driver implementations. */
/*  * */
/*  * $Horde: mnemo/lib/Driver/kolab.php,v 1.25 2009/07/14 00:25:35 mrubinsk Exp $ */
/*  * */
/*  * Copyright 2004-2011 The Horde Project (http://www.horde.org/) */
/*  * */
/*  * See the enclosed file LICENSE for license information (ASL). If you */
/*  * did not receive this file, see http://www.horde.org/licenses/asl.php. */
/*  * */
/*  * @author  Gunnar Wrobel <wrobel@pardus.de> */
/*  * @since   Mnemo 2.0 */
/*  * @package Mnemo */
/*  *\/ */
/* class Mnemo_Driver_kolab_wrapper { */

/*     /\** */
/*      * Indicates if the wrapper has connected or not */
/*      * */
/*      * @var boolean */
/*      *\/ */
/*     var $_connected = false; */

/*     /\** */
/*      * String containing the current notepad name. */
/*      * */
/*      * @var string */
/*      *\/ */
/*     var $_notepad = ''; */

/*     /\** */
/*      * Our Kolab server connection. */
/*      * */
/*      * @var Kolab */
/*      *\/ */
/*     var $_kolab = null; */

/*     /\** */
/*      * Our parent driver. */
/*      * */
/*      * @var Mnemo_Driver */
/*      *\/ */
/*     var $_driver; */

/*     /\** */
/*      * Constructor */
/*      * */
/*      * @param string      $notepad  The notepad to load. */
/*      * @param Horde_Kolab $kolab    The Kolab connection object */
/*      *\/ */
/*     function Mnemo_Driver_kolab_wrapper(&$driver) */
/*     { */
/*         $this->_notepad = $driver->_notepad; */
/*         $this->_kolab = &$driver->_kolab; */
/*         // Required for the encrypt() function */
/*         $this->_driver = &$driver; */
/*     } */

/*     /\** */
/*      * Connect to the Kolab backend */
/*      * */
/*      * @param int    $loader         The version of the XML */
/*      *                               loader */
/*      * */
/*      * @return mixed True on success, a PEAR error otherwise */
/*      *\/ */
/*     function connect($loader = 0) */
/*     { */
/*         if ($this->_connected) { */
/*             return true; */
/*         } */

/*         $result = $this->_kolab->open($this->_notepad, $loader); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         $this->_connected = true; */

/*         return true; */
/*     } */

/*     /\** */
/*      * Encrypts a note. */
/*      * */
/*      * @param string $note        The note text. */
/*      * @param string $passphrase  The passphrase to encrypt the note with. */
/*      * */
/*      * @return string|PEAR_Error  The encrypted text or PEAR_Error on failure. */
/*      *\/ */
/*     function encrypt($note, $passphrase) */
/*     { */
/*         return $this->_driver->encrypt($note, $passphrase); */
/*     } */

/*     /\** */
/*      * Decrypts a note. */
/*      * */
/*      * @param string $note        The encrypted note text. */
/*      * @param string $passphrase  The passphrase to decrypt the note with. */
/*      * */
/*      * @return string|PEAR_Error  The decrypted text or PEAR_Error on failure. */
/*      *\/ */
/*     function decrypt($note, $passphrase) */
/*     { */
/*         return $this->_driver->decrypt($note, $passphrase); */
/*     } */
/* } */


/* /\** */
/*  * Old Horde Mnemo driver for the Kolab IMAP server. */
/*  * */
/*  * $Horde: mnemo/lib/Driver/kolab.php,v 1.25 2009/07/14 00:25:35 mrubinsk Exp $ */
/*  * */
/*  * Copyright 2004-2011 The Horde Project (http://www.horde.org/) */
/*  * */
/*  * See the enclosed file LICENSE for license information (ASL). If you */
/*  * did not receive this file, see http://www.horde.org/licenses/asl.php. */
/*  * */
/*  * @author  Stuart Binge <omicron@mighty.co.za> */
/*  * @since   Mnemo 2.0 */
/*  * @package Mnemo */
/*  *\/ */
/* class Mnemo_Driver_kolab_wrapper_old extends Mnemo_Driver_kolab_wrapper { */

/*     function _buildNote() */
/*     { */
/*         return array( */
/*             'memolist_id' => $this->_notepad, */
/*             'memo_id' => $this->_kolab->getUID(), */
/*             'uid' => $this->_kolab->getUID(), */
/*             'desc' => $this->_kolab->getStr('summary'), */
/*             'body' => $this->_kolab->getStr('body'), */
/*             'category' => $this->_kolab->getStr('categories'), */
/*             'encrypted' => false, */
/*         ); */
/*     } */

/*     /\** */
/*      * Retrieve one note by UID. */
/*      * */
/*      * @param string $uid  The UID of the note to retrieve. */
/*      * */
/*      * @return array  The array of note attributes. */
/*      *\/ */
/*     function getByUID($uid) */
/*     { */
/*         return PEAR::raiseError('Not supported'); */
/*     } */

/*     function _setObject($desc, $body, $category = '', $uid = null) */
/*     { */
/*         if (isset($uid)) { */
/*             $result = $this->_kolab->loadObject($uid); */
/*         } else { */
/*             $uid = strval(new Horde_Support_Uuid()); */
/*             $result = $this->_kolab->newObject($uid); */
/*         } */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         $this->_kolab->setStr('summary', $desc); */
/*         $this->_kolab->setStr('body', $body); */
/*         $this->_kolab->setStr('categories', $category); */

/*         $result = $this->_kolab->saveObject(); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         return $uid; */
/*     } */

/*     /\** */
/*      * Modify an existing note. */
/*      * */
/*      * @param integer $noteId   The note to modify. */
/*      * @param string $desc      The description (long) of the note. */
/*      * @param string $body      The description (long) of the note. */
/*      * @param string $category  The category of the note. */
/*      *\/ */
/*     function modify($noteId, $desc, $body, $category = '') */
/*     { */
/*         $result = $this->_setObject($desc, $body, $category, $noteId); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         return $result == $noteId; */
/*     } */

/*     /\** */
/*      * Move a note to a new notepad. */
/*      * */
/*      * @param string $noteId      The note to move. */
/*      * @param string $newNotepad  The new notepad. */
/*      *\/ */
/*     function move($noteId, $newNotepad) */
/*     { */
/*         return $this->_kolab->moveObject($noteId, $newNotepad); */
/*     } */

/*     function delete($noteId) */
/*     { */
/*         return $this->_kolab->removeObjects($noteId); */
/*     } */

/*     function deleteAll() */
/*     { */
/*         return $this->_kolab->removeAllObjects(); */
/*     } */


/* } */

/* /\** */
/*  * New Horde Mnemo driver for the Kolab IMAP server. */
/*  * */
/*  * $Horde: mnemo/lib/Driver/kolab.php,v 1.25 2009/07/14 00:25:35 mrubinsk Exp $ */
/*  * */
/*  * Copyright 2004-2011 The Horde Project (http://www.horde.org/) */
/*  * */
/*  * See the enclosed file LICENSE for license information (ASL). If you */
/*  * did not receive this file, see http://www.horde.org/licenses/asl.php. */
/*  * */
/*  * @author  Gunnar Wrobel <wrobel@pardus.de> */
/*  * @since   Mnemo 2.0 */
/*  * @package Mnemo */
/*  *\/ */
/* class Mnemo_Driver_kolab_wrapper_new extends Mnemo_Driver_kolab_wrapper { */

/*     /\** */
/*      * Shortcut to the imap connection */
/*      * */
/*      * @var Kolab_IMAP */
/*      *\/ */
/*     var $_store = null; */

/*     /\** */
/*      * Connect to the Kolab backend */
/*      * */
/*      * @return mixed True on success, a PEAR error otherwise */
/*      *\/ */
/*     function connect() */
/*     { */
/*         if ($this->_connected) { */
/*             return true; */
/*         } */

/*         $result = parent::connect(1); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         $this->_store = &$this->_kolab->_storage; */

/*         return true; */
/*     } */

/*     /\** */
/*      * Split the notepad name of the id. We use this to make ids */
/*      * unique across folders. */
/*      * */
/*      * @param string $id The ID of the note appended with the notepad */
/*      *                   name. */
/*      * */
/*      * @return array  The note id and notepad name */
/*      *\/ */
/*     function _splitId($id) */
/*     { */
/*         $split = explode('@', $id, 2); */
/*         if (count($split) == 2) { */
/*             list($id, $notepad) = $split; */
/*         } else if (count($split) == 1) { */
/*             $notepad = $GLOBALS['registry']->getAuth(); */
/*         } */
/*         return array($id, $notepad); */
/*     } */

/*     /\** */
/*      * Retrieve one note by UID. */
/*      * */
/*      * @param string $uid         The UID of the note to retrieve. */
/*      * @param string $passphrase  A passphrase with which this note was */
/*      *                            supposed to be encrypted. */
/*      * */
/*      * @return array  The array of note attributes. */
/*      *\/ */
/*     function getByUID($uid, $passphrase = null) */
/*     { */
/*         list($noteId, $notepad) = $this->_splitId($uid); */

/*         if ($this->_notepad != $notepad) { */
/*             $this->_notepad = $notepad; */
/*             $this->_connected = false; */
/*             $this->connect(); */
/*         } */

/*         return $this->get($noteId, $passphrase); */
/*     } */

/*     /\** */
/*      * Add or modify a note. */
/*      * */
/*      * @param string $desc        The description (long) of the note. */
/*      * @param string $body        The description (long) of the note. */
/*      * @param string $category    The category of the note. */
/*      * @param string $uid         The note to modify. */
/*      * @param string $passphrase  The passphrase to encrypt the note with. */
/*      * */
/*      * @return mixed The id of the note if successful, a PEAR error */
/*      * otherwise */
/*      *\/ */
/*     function _setObject($desc, $body, $category = '', $uid = null, $passphrase = null) */
/*     { */
/*         if (empty($uid)) { */
/*             $note_uid = strval(new Horde_Support_Uuid()); */
/*             $old_uid = null; */
/*             $action = array('action' => 'add'); */
/*         } else { */
/*             list($note_uid, $notepad) = $this->_splitId($uid); */
/*             $old_uid = $note_uid; */
/*             $action = array('action' => 'modify'); */
/*         } */

/*         if ($passphrase) { */
/*             $body = $this->_encrypt($body, $passphrase); */
/*             if (is_a($body, 'PEAR_Error')) { */
/*                 return $body; */
/*             } */
/*             Mnemo::storePassphrase($note_uid, $passphrase); */
/*         } */

/*         $result = $this->_store->save(array('uid' => $note_uid, */
/*                                             'desc' => $desc, */
/*                                             'body' => $body, */
/*                                             'categories' => $category, */
/*                                             ), */
/*                                       $old_uid); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         /\* Log the action in the history log. *\/ */
/*         $history = $GLOBALS['injector']->getInstance('Horde_History'); */
/*         $history->log('mnemo:' . $this->_notepad . ':' . $this->_uniqueId($note_uid), $action, true); */

/*         return $this->_uniqueId($note_uid); */
/*     } */

/*     /\** */
/*      * Modify an existing note. */
/*      * */
/*      * @param integer $noteId   The note to modify. */
/*      * @param string $desc      The description (long) of the note. */
/*      * @param string $body      The description (long) of the note. */
/*      * @param string $category  The category of the note. */
/*      * @param string $passphrase  The passphrase to encrypt the note with. */
/*      * */
/*      * @return booelan  True if successful, a PEAR error otherwise. */
/*      *\/ */
/*     function modify($noteId, $desc, $body, $category = '', $passphrase = null) */
/*     { */
/*         $result = $this->_setObject($desc, $body, $category, $noteId, $passphrase); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         return $result == $noteId; */
/*     } */

/*     /\** */
/*      * Move a note to a new notepad. */
/*      * */
/*      * @param string $noteId      The note to move. */
/*      * @param string $newNotepad  The new notepad. */
/*      * */
/*      * @return mixed  True on success, PEAR_Error on failure. */
/*      *\/ */
/*     function move($noteId, $newNotepad) */
/*     { */
/*         list($noteId, $notepad) = $this->_splitId($noteId); */

/*         return $this->_store->move($noteId, $newNotepad); */
/*     } */

/*     /\** */
/*      * Delete the specified note from the current notepad */
/*      * */
/*      * @param string $noteId      The note to delete. */
/*      * */
/*      * @return mixed  True on success, PEAR_Error on failure. */
/*      *\/ */
/*     function delete($noteId) */
/*     { */
/*         list($noteId, $notepad) = $this->_splitId($noteId); */

/*         $result = $this->_store->delete($noteId); */
/*         if (is_a($result, 'PEAR_Error')) { */
/*             return $result; */
/*         } */

/*         $history = $GLOBALS['injector']->getInstance('Horde_History'); */
/*         $history->log('mnemo:' . $this->_notepad . ':' . $this->_uniqueId($noteId), array('action' => 'delete'), true); */

/*         return $result; */
/*     } */

/*     /\** */
/*      * Delete all notes from the current notepad */
/*      * */
/*      * @return mixed  True on success, PEAR_Error on failure. */
/*      *\/ */
/*     function deleteAll() */
/*     { */
/*         return $this->_store->deleteAll(); */
/*     } */


/* } */
