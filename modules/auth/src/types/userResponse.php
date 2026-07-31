<?php

namespace Vatger\Auth\Types;

class UserResponse
{
    public string $cid;
    public string $name_first;
    public string $name_last;
    public string $email;

    public function __construct(array $body)
    {
        $user = $body['data'];

        $this->cid = $user["cid"];
        $this->name_first = $user["personal"]["name_first"];
        $this->name_last = $user["personal"]["name_last"];
        $this->email = $user["personal"]["email"];
    }
}

class VatgerUserResponse 
{
    public string $cid;
    public string $name_first;
    public string $name_last;
    public string $email;

    public function __construct(array $body)
    {
        $this->cid = $body["id"];
        $this->name_first = $body["firstname"];
        $this->name_last = $body["lastname"];
        $this->email = $body["email"];
    }
}