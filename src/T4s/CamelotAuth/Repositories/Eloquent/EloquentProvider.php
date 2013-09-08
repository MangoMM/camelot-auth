<?php namespace T4s\CamelotAuth\Repositories\Eloquent;


class EloquentProvider
{

	protected $model;

	public function __construct($model = null)
	{
		if(isset($model))
		{
			$this->model = $model;
		}
	}

	public function createModel()
	{
		$class = '\\'.ltrim($this->model,'\\');

		return new $class;
	}
}