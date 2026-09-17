<?php

namespace App\Services\Auth;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(): string
    {
        $bytes=random_bytes(20); $bits=''; $result='';
        foreach(str_split($bytes) as $byte) $bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT);
        foreach(str_split($bits,5) as $chunk) $result.=self::ALPHABET[bindec(str_pad($chunk,5,'0'))];
        return $result;
    }

    public function verify(string $secret,string $code,?int $timestamp=null): bool
    {
        if(!preg_match('/^\d{6}$/',$code)) return false;
        $counter=intdiv($timestamp ?? time(),30);
        foreach([-1,0,1] as $window) if(hash_equals($this->code($secret,$counter+$window),$code)) return true;
        return false;
    }

    public function uri(string $secret,string $email,string $issuer): string
    {
        $label=rawurlencode($issuer.':'.$email);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private function code(string $secret,int $counter): string
    {
        $key=$this->decode($secret);
        $binary=pack('N2',intdiv($counter,4294967296),$counter%4294967296);
        $hash=hash_hmac('sha1',$binary,$key,true); $offset=ord($hash[19])&15;
        $value=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255);
        return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
    }

    private function decode(string $secret): string
    {
        $secret=strtoupper(preg_replace('/[^A-Z2-7]/','',$secret)??''); $bits=''; $result='';
        foreach(str_split($secret) as $char) $bits.=str_pad(decbin(strpos(self::ALPHABET,$char)),5,'0',STR_PAD_LEFT);
        foreach(str_split($bits,8) as $byte) if(strlen($byte)===8) $result.=chr(bindec($byte));
        return $result;
    }
}
