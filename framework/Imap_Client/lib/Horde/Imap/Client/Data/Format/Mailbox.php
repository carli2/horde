<?php
/**
 * Copyright 2012-2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2012-2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */

/**
 * Object representation of an IMAP mailbox string (RFC 3501 [9]).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */
class Horde_Imap_Client_Data_Format_Mailbox extends Horde_Imap_Client_Data_Format_Astring
{
    /**
     * Mailbox object.
     *
     * @var Horde_Imap_Client_Mailbox
     */
    protected $_mailbox;

    /**
     * @param mixed $data  Either a mailbox object or a UTF-8 mailbox name.
     */
    public function __construct($data)
    {
        $this->_mailbox = Horde_Imap_Client_Mailbox::get($data);

        parent::__construct($this->_mailbox->utf7imap);
    }

    /**
     */
    public function __toString()
    {
        return strval($this->_mailbox);
    }

    /**
     */
    public function getData()
    {
        return $this->_mailbox;
    }

    /**
     */
    public function length()
    {
        return strlen($this->_mailbox->utf7imap);
    }

    /**
     */
    public function getStream()
    {
        $stream = new Horde_Stream_Temp();
        $stream->add($this->_mailbox->utf7imap);
        return $stream;
    }

}
