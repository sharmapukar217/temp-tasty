<?php namespace Acme\Extension\ApiResources\Transformers;

use League\Fractal\TransformerAbstract;

class OrdersApiTransformer extends TransformerAbstract
{
	public function transform($resource)
	{
        return $resource->toArray();
	}
}