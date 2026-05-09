<?php

namespace ChildTheme\Tests\Unit\Providers\Shop;

use PHPUnit\Framework\TestCase;

/**
 * Structural test for the product CPT ACF field group — pinned so a
 * future refactor that drops or renames a field is caught at CI time
 * instead of in production.
 */
class ProductFieldsAcfTest extends TestCase
{
    private array $group;

    protected function setUp(): void
    {
        $path = __DIR__ . '/../../../../src/Providers/Shop/acf-json/group_product_fields.json';
        $this->assertFileExists($path);
        $this->group = json_decode(file_get_contents($path), true);
        $this->assertIsArray($this->group);
    }

    public function testGraphqlFieldNameIsProductFields(): void
    {
        $this->assertSame('productFields', $this->group['graphql_field_name']);
    }

    public function testSkipShippingAtCheckoutFieldExists(): void
    {
        $field = $this->findField('skip_shipping_at_checkout');
        $this->assertNotNull(
            $field,
            'skip_shipping_at_checkout flag must exist on group_product_fields — drives the speculative-vs-committed checkout shipping decision'
        );

        $this->assertSame('true_false', $field['type']);
        $this->assertSame(0, $field['default_value'], 'Defaults to unticked = committed (existing checkout flow)');
        $this->assertSame(1, $field['show_in_graphql'], 'Frontend needs the flag to surface speculative copy on the thank-you page');
    }

    private function findField(string $name): ?array
    {
        foreach ($this->group['fields'] ?? [] as $field) {
            if (($field['name'] ?? null) === $name) {
                return $field;
            }
        }
        return null;
    }
}
