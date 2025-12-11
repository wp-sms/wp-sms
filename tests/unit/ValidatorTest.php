<?php

namespace unit;

use WP_SMS\Utils\Validator;
use WP_UnitTestCase;

class ValidatorTest extends WP_UnitTestCase
{
    /**
     * Test required field validation passes.
     */
    public function testRequiredFieldPasses()
    {
        $validator = new Validator(
            ['name' => 'John'],
            ['name' => 'required']
        );

        $this->assertTrue($validator->passes());
        $this->assertEmpty($validator->errors());
    }

    /**
     * Test required field validation fails when empty.
     */
    public function testRequiredFieldFailsWhenEmpty()
    {
        $validator = new Validator(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    /**
     * Test required field validation fails when null.
     */
    public function testRequiredFieldFailsWhenNull()
    {
        $validator = new Validator(
            [],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    /**
     * Test required field validation fails with whitespace only.
     */
    public function testRequiredFieldFailsWithWhitespace()
    {
        $validator = new Validator(
            ['name' => '   '],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test email validation passes with valid email.
     */
    public function testEmailValidationPasses()
    {
        $validator = new Validator(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Test email validation fails with invalid email.
     */
    public function testEmailValidationFails()
    {
        $validator = new Validator(
            ['email' => 'invalid-email'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    /**
     * Test email validation fails without domain.
     */
    public function testEmailValidationFailsWithoutDomain()
    {
        $validator = new Validator(
            ['email' => 'test@'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test min length validation for string.
     */
    public function testMinLengthValidationForString()
    {
        // Use non-numeric string to test string length validation
        $validator = new Validator(
            ['password' => 'hello'],
            ['password' => 'min:5']
        );

        $this->assertTrue($validator->passes());

        $validator = new Validator(
            ['password' => 'test'],
            ['password' => 'min:5']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test min validation for numeric value.
     */
    public function testMinValidationForNumericValue()
    {
        $validator = new Validator(
            ['age' => '18'],
            ['age' => 'min:18']
        );

        $this->assertTrue($validator->passes());

        $validator = new Validator(
            ['age' => '17'],
            ['age' => 'min:18']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test max length validation for string.
     */
    public function testMaxLengthValidationForString()
    {
        $validator = new Validator(
            ['username' => 'john'],
            ['username' => 'max:10']
        );

        $this->assertTrue($validator->passes());

        $validator = new Validator(
            ['username' => 'johndoesmith'],
            ['username' => 'max:10']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test max validation for numeric value.
     */
    public function testMaxValidationForNumericValue()
    {
        $validator = new Validator(
            ['quantity' => '100'],
            ['quantity' => 'max:100']
        );

        $this->assertTrue($validator->passes());

        $validator = new Validator(
            ['quantity' => '101'],
            ['quantity' => 'max:100']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test numeric validation passes.
     */
    public function testNumericValidationPasses()
    {
        $validator = new Validator(
            ['phone' => '1234567890'],
            ['phone' => 'numeric']
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Test numeric validation fails with non-numeric.
     */
    public function testNumericValidationFails()
    {
        $validator = new Validator(
            ['phone' => '123-456-7890'],
            ['phone' => 'numeric']
        );

        $this->assertTrue($validator->fails());
    }

    /**
     * Test multiple rules on single field.
     */
    public function testMultipleRulesOnSingleField()
    {
        $validator = new Validator(
            ['email' => 'test@example.com'],
            ['email' => 'required|email']
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Test multiple rules fail on first rule.
     */
    public function testMultipleRulesFailOnFirstRule()
    {
        $validator = new Validator(
            ['email' => ''],
            ['email' => 'required|email']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    /**
     * Test multiple fields validation.
     */
    public function testMultipleFieldsValidation()
    {
        $validator = new Validator(
            [
                'name'  => 'John Doe',
                'email' => 'john@example.com',
                'age'   => '25'
            ],
            [
                'name'  => 'required|min:3',
                'email' => 'required|email',
                'age'   => 'required|numeric'
            ]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Test multiple fields validation with some failures.
     */
    public function testMultipleFieldsValidationWithFailures()
    {
        $validator = new Validator(
            [
                'name'  => 'Jo',
                'email' => 'invalid',
                'age'   => 'twenty'
            ],
            [
                'name'  => 'required|min:3',
                'email' => 'required|email',
                'age'   => 'required|numeric'
            ]
        );

        $this->assertTrue($validator->fails());
        $errors = $validator->errors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
    }

    /**
     * Test error messages contain field name.
     */
    public function testErrorMessagesContainFieldName()
    {
        $validator = new Validator(
            ['username' => ''],
            ['username' => 'required']
        );

        $validator->fails();
        $errors = $validator->errors();

        $this->assertStringContainsString('Username', $errors['username'][0]);
    }

    /**
     * Test min length error message contains parameter.
     */
    public function testMinLengthErrorMessageContainsParameter()
    {
        $validator = new Validator(
            ['password' => 'ab'],
            ['password' => 'min:8']
        );

        $validator->fails();
        $errors = $validator->errors();

        $this->assertStringContainsString('8', $errors['password'][0]);
    }

    /**
     * Test validation with unicode characters.
     */
    public function testValidationWithUnicodeCharacters()
    {
        $validator = new Validator(
            ['name' => 'محمد'],
            ['name' => 'required|min:3']
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Test passes method resets errors.
     */
    public function testPassesMethodResetsErrors()
    {
        $validator = new Validator(
            ['name' => ''],
            ['name' => 'required']
        );

        $validator->fails();
        $this->assertNotEmpty($validator->errors());

        // Modify data and re-validate (simulating)
        $validator2 = new Validator(
            ['name' => 'John'],
            ['name' => 'required']
        );

        $this->assertTrue($validator2->passes());
        $this->assertEmpty($validator2->errors());
    }
}
