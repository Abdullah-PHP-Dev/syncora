<?php

namespace App\Services;

class AdService
{
public function create(array $data)
{
return DB::transaction(function () use ($data) {

return Ad::create($data);

});
}
}