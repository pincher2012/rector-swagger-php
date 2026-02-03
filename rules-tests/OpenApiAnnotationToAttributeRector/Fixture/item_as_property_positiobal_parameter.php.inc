<?php

use OpenApi\Annotations as OA;

/**
 * @OA\Property(
 *     property="articles",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/Article")
 * )
 */
class Order
{
}

?>
-----
<?php

use OpenApi\Attributes as OA;

#[OA\Property(property: 'articles', type: 'array', items: new OA\Items(ref: '#/components/schemas/Article'))]
class Order
{
}

?>
