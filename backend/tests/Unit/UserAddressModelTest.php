<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\UserAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAddressModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'type',
            'first_name',
            'last_name',
            'company',
            'address_line_1',
            'address_line_2',
            'city',
            'province',
            'postal_code',
            'country',
            'phone',
            'is_default',
        ];

        $address = new UserAddress();
        $this->assertEquals($fillable, $address->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $address = UserAddress::factory()->create([
            'is_default' => true,
        ]);

        $this->assertTrue($address->is_default);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $address->user);
        $this->assertEquals($user->id, $address->user->id);
    }

    /** @test */
    public function it_gets_full_name()
    {
        $address = UserAddress::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $address->getFullNameAttribute());
    }

    /** @test */
    public function it_handles_empty_names_in_full_name()
    {
        $address = UserAddress::factory()->create([
            'first_name' => 'John',
            'last_name' => '',
        ]);

        $this->assertEquals('John', $address->getFullNameAttribute());

        $address = UserAddress::factory()->create([
            'first_name' => '',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('Doe', $address->getFullNameAttribute());
    }

    /** @test */
    public function it_gets_formatted_address()
    {
        $address = UserAddress::factory()->create([
            'address_line_1' => '123 Main Street',
            'address_line_2' => 'Apt 4B',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
        ]);

        $expected = '123 Main Street, Apt 4B, Manila, Metro Manila, 1000, Philippines';
        $this->assertEquals($expected, $address->getFormattedAddressAttribute());
    }

    /** @test */
    public function it_handles_empty_address_fields_in_formatted_address()
    {
        $address = UserAddress::factory()->create([
            'address_line_1' => '123 Main Street',
            'address_line_2' => '', // Empty
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
        ]);

        $expected = '123 Main Street, Manila, Metro Manila, 1000, Philippines';
        $this->assertEquals($expected, $address->getFormattedAddressAttribute());
    }

    /** @test */
    public function it_handles_all_empty_address_fields()
    {
        $address = UserAddress::factory()->create([
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'province' => '',
            'postal_code' => '',
            'country' => '',
        ]);

        $this->assertEquals('', $address->getFormattedAddressAttribute());
    }

    /** @test */
    public function it_handles_different_address_types()
    {
        $shippingAddress = UserAddress::factory()->create(['type' => 'shipping']);
        $billingAddress = UserAddress::factory()->create(['type' => 'billing']);

        $this->assertEquals('shipping', $shippingAddress->type);
        $this->assertEquals('billing', $billingAddress->type);
    }

    /** @test */
    public function it_can_be_set_as_default()
    {
        $defaultAddress = UserAddress::factory()->create(['is_default' => true]);
        $nonDefaultAddress = UserAddress::factory()->create(['is_default' => false]);

        $this->assertTrue($defaultAddress->is_default);
        $this->assertFalse($nonDefaultAddress->is_default);
    }

    /** @test */
    public function it_stores_company_information()
    {
        $address = UserAddress::factory()->create([
            'company' => 'Acme Corporation',
        ]);

        $this->assertEquals('Acme Corporation', $address->company);
    }

    /** @test */
    public function it_handles_null_company()
    {
        $address = UserAddress::factory()->create([
            'company' => null,
        ]);

        $this->assertNull($address->company);
    }

    /** @test */
    public function it_stores_phone_number()
    {
        $address = UserAddress::factory()->create([
            'phone' => '+63 912 345 6789',
        ]);

        $this->assertEquals('+63 912 345 6789', $address->phone);
    }

    /** @test */
    public function it_handles_philippine_address_format()
    {
        $address = UserAddress::factory()->create([
            'address_line_1' => '123 Rizal Street',
            'address_line_2' => 'Barangay San Antonio',
            'city' => 'Makati City',
            'province' => 'Metro Manila',
            'postal_code' => '1200',
            'country' => 'Philippines',
        ]);

        $expected = '123 Rizal Street, Barangay San Antonio, Makati City, Metro Manila, 1200, Philippines';
        $this->assertEquals($expected, $address->getFormattedAddressAttribute());
    }

    /** @test */
    public function it_trims_whitespace_in_full_name()
    {
        $address = UserAddress::factory()->create([
            'first_name' => '  John  ',
            'last_name' => '  Doe  ',
        ]);

        $this->assertEquals('John   Doe', $address->getFullNameAttribute());
    }
}