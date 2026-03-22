<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\RegistrationForm;
use WSms\Auth\RegistrationFormRepository;

class RegistrationFormRepositoryTest extends TestCase
{
    private RegistrationFormRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $this->repo = new RegistrationFormRepository();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    public function testSaveAndFind(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Test Form',
            slug: 'test-form',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        $id = $this->repo->save($form);
        $this->assertNotEmpty($id);

        $found = $this->repo->find($id);
        $this->assertNotNull($found);
        $this->assertSame('Test Form', $found->getName());
        $this->assertSame('test-form', $found->getSlug());
    }

    public function testFindBySlug(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Vendor Form',
            slug: 'vendor-registration',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        $this->repo->save($form);

        $found = $this->repo->findBySlug('vendor-registration');
        $this->assertNotNull($found);
        $this->assertSame('Vendor Form', $found->getName());

        $notFound = $this->repo->findBySlug('nonexistent');
        $this->assertNull($notFound);
    }

    public function testFindAll(): void
    {
        $this->assertCount(0, $this->repo->findAll());

        $this->repo->save(new RegistrationForm(id: '', name: 'Form 1', slug: 'form-1', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));
        $this->repo->save(new RegistrationForm(id: '', name: 'Form 2', slug: 'form-2', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));

        $this->assertCount(2, $this->repo->findAll());
    }

    public function testUpdateExistingForm(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Original',
            slug: 'original',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        $id = $this->repo->save($form);

        $updated = new RegistrationForm(
            id: $id,
            name: 'Updated',
            slug: 'original',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        $this->repo->save($updated);

        $this->assertCount(1, $this->repo->findAll());
        $this->assertSame('Updated', $this->repo->find($id)->getName());
    }

    public function testDelete(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'To Delete',
            slug: 'to-delete',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        $id = $this->repo->save($form);
        $this->assertCount(1, $this->repo->findAll());

        $result = $this->repo->delete($id);
        $this->assertTrue($result);
        $this->assertCount(0, $this->repo->findAll());
    }

    public function testDeleteNonexistent(): void
    {
        $this->assertFalse($this->repo->delete('nonexistent'));
    }

    public function testCount(): void
    {
        $this->assertSame(0, $this->repo->count());

        $this->repo->save(new RegistrationForm(id: '', name: 'A', slug: 'a', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));
        $this->assertSame(1, $this->repo->count());

        $this->repo->save(new RegistrationForm(id: '', name: 'B', slug: 'b', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));
        $this->assertSame(2, $this->repo->count());
    }

    public function testDuplicateSlugThrows(): void
    {
        $this->repo->save(new RegistrationForm(id: '', name: 'First', slug: 'duplicate', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('slug already exists');

        $this->repo->save(new RegistrationForm(id: '', name: 'Second', slug: 'duplicate', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));
    }

    public function testReservedSlugThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        $this->repo->save(new RegistrationForm(id: '', name: 'Login Form', slug: 'login', fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]]));
    }

    public function testSameSlugOnSameFormDoesNotThrow(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Form',
            slug: 'my-form',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        $id = $this->repo->save($form);

        $updated = new RegistrationForm(
            id: $id,
            name: 'Updated Form',
            slug: 'my-form',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
        );

        // Should not throw — same slug on same form
        $this->repo->save($updated);
        $this->assertSame('Updated Form', $this->repo->find($id)->getName());
    }

    public function testFindReturnsNullForMissingId(): void
    {
        $this->assertNull($this->repo->find('nonexistent'));
    }

    public function testOptionNotArrayReturnsEmpty(): void
    {
        $GLOBALS['_test_options']['wsms_registration_forms'] = 'invalid';
        $this->assertCount(0, $this->repo->findAll());
    }
}
