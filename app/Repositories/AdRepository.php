<?php

class AdRepository
{
	public function featured()
	{
		return Ad::query()
			->where('is_featured',1)
			->latest()
			->paginate(20);
	}
}