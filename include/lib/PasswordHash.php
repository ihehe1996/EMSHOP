<?php
/**
 * Portable PHP password hashing framework.
 *
 * Copyright (c) 2005-2012 Solar Designer
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted.
 *
 * https://www.openwall.com/phpass/
 */
class PasswordHash
{
    var $itoa64;
    var $iteration_count_log2;
    var $portable_hashes;
    var $random_state;

    function __construct($iteration_count_log2, $portable_hashes)
    {
        $this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31) {
            $iteration_count_log2 = 8;
        }
        $this->iteration_count_log2 = $iteration_count_log2;
        $this->portable_hashes = $portable_hashes;
        $this->random_state = microtime() . uniqid((string) mt_rand(), true);
    }

    function get_random_bytes($count)
    {
        $output = '';
        if (@is_readable('/dev/urandom') && ($fh = @fopen('/dev/urandom', 'rb'))) {
            $output = fread($fh, $count);
            fclose($fh);
        }

        if (strlen($output) < $count) {
            $output = '';
            for ($i = 0; $i < $count; $i += 16) {
                $this->random_state = md5(microtime() . $this->random_state);
                $output .= pack('H*', md5($this->random_state));
            }
            $output = substr($output, 0, $count);
        }

        return $output;
    }

    function encode64($input, $count)
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= $this->itoa64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $this->itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $this->itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= $this->itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }

    function gensalt_private($input)
    {
        $output = '$P$';
        $output .= $this->itoa64[min($this->iteration_count_log2 + 5, 30)];
        $output .= $this->encode64($input, 6);

        return $output;
    }

    function crypt_private($password, $setting)
    {
        $output = '*0';
        if (substr($setting, 0, 2) === $output) {
            $output = '*1';
        }

        $id = substr($setting, 0, 3);
        if ($id !== '$P$' && $id !== '$H$') {
            return $output;
        }

        $count_log2 = strpos($this->itoa64, $setting[3]);
        if ($count_log2 < 7 || $count_log2 > 30) {
            return $output;
        }

        $count = 1 << $count_log2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $output;
        }

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($setting, 0, 12);
        $output .= $this->encode64($hash, 16);

        return $output;
    }

    function HashPassword($password)
    {
        $random = $this->get_random_bytes(6);
        $hash = $this->crypt_private($password, $this->gensalt_private($random));
        if (strlen($hash) === 34) {
            return $hash;
        }

        return '*';
    }

    function CheckPassword($password, $stored_hash)
    {
        $hash = $this->crypt_private($password, $stored_hash);
        if ($hash[0] === '*') {
            $hash = crypt($password, $stored_hash);
        }

        return hash_equals($stored_hash, $hash);
    }
}
