<?php

namespace Illuminate\Tests\Database;

use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\CrossJoinSequence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Tests\Database\Fixtures\Models\Money\Price;
use Mockery;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentFactoryTest extends TestCase
{
    protected function setUp()
    {
        $container = Container::getInstance();
        $container->singleton(Generator::class, function () {
            return \Faker\Factory::create('en_US');
        });
        $container->instance(Application::class, $app = Mockery::mock(Application::class));

        $app->shouldReceive('getNamespace')->andReturn('App\\');

        Facade::setFacadeApplication($container);

        $container->instance('events', new Dispatcher($container));

        $db = new DB();

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    public function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('options')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->bigInteger('user_id');
            $table->string('title');
            $table->timestamps();
        });

        $this->schema()->create('comments', function ($table) {
            $table->increments('id');
            $table->bigInteger('commentable_id');
            $table->string('commentable_type');
            $table->string('body');
            $table->timestamps();
        });

        $this->schema()->create('roles', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        $this->schema()->create('role_user', function ($table) {
            $table->bigInteger('role_id');
            $table->bigInteger('user_id');
            $table->string('admin')->default('N');
        });
    }

    protected function tearDown()
    {
        Mockery::close();

        $this->schema()->drop('users');

        Container::setInstance(null);
    }

    /** @test */
    public function it_can_create_basic_models()
    {
        $user = FactoryTestUserFactory::newFactory()->create();
        $this->assertInstanceOf(Eloquent::class, $user);

        $user = FactoryTestUserFactory::newFactory()->createOne();
        $this->assertInstanceOf(Eloquent::class, $user);

        $user = FactoryTestUserFactory::newFactory()->create(['name' => 'Taylor Otwell']);
        $this->assertInstanceOf(Eloquent::class, $user);
        $this->assertSame('Taylor Otwell', $user->name);

        $users = FactoryTestUserFactory::newFactory()->createMany([
            ['name' => 'Taylor Otwell'],
            ['name' => 'Jeffrey Way'],
        ]);
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(2, $users);

        $users = FactoryTestUserFactory::times(10)->create();
        $this->assertCount(10, $users);
    }

    /** @test */
    public function it_expands_closure_attributes_and_passes_to_closures()
    {
        $user = FactoryTestUserFactory::newFactory()->create([
            'name' => function () {
                return 'taylor';
            },
            'options' => function ($attributes) {
                return $attributes['name'].'-options';
            },
        ]);

        $this->assertSame('taylor-options', $user->options);
    }

    /** @test */
    public function it_makes_unpersisted_model_instances()
    {
        $user = FactoryTestUserFactory::newFactory()->makeOne();
        $this->assertInstanceOf(Eloquent::class, $user);

        $user = FactoryTestUserFactory::newFactory()->make(['name' => 'Taylor Otwell']);

        $this->assertInstanceOf(Eloquent::class, $user);
        $this->assertSame('Taylor Otwell', $user->name);
        $this->assertCount(0, FactoryTestUser::all());
    }

    /** @test */
    public function it_can_create_models_with_basic_attributes()
    {
        $user = FactoryTestUserFactory::newFactory()->raw();
        $this->assertIsArray($user);

        $user = FactoryTestUserFactory::newFactory()->raw(['name' => 'Taylor Otwell']);
        $this->assertIsArray($user);
        $this->assertSame('Taylor Otwell', $user['name']);
    }

    /** @test */
    public function it_can_create_models_with_expanded_attributes()
    {
        $post = FactoryTestPostFactory::newFactory()->raw();
        $this->assertIsArray($post);

        $post = FactoryTestPostFactory::newFactory()->raw(['title' => 'Test Title']);
        $this->assertIsArray($post);
        $this->assertIsInt($post['user_id']);
        $this->assertSame('Test Title', $post['title']);
    }

    /** @test */
    public function it_can_create_models_with_lazy_attributes()
    {
        $userFunction = FactoryTestUserFactory::newFactory()->lazy();
        $this->assertIsCallable($userFunction);
        $this->assertInstanceOf(Eloquent::class, $userFunction());

        $userFunction = FactoryTestUserFactory::newFactory()->lazy(['name' => 'Taylor Otwell']);
        $this->assertIsCallable($userFunction);

        $user = $userFunction();
        $this->assertInstanceOf(Eloquent::class, $user);
        $this->assertSame('Taylor Otwell', $user->name);
    }

    /** @test */
    public function it_can_create_multiple_models()
    {
        $posts = FactoryTestPostFactory::newFactory()->times(10)->raw();
        $this->assertIsArray($posts);

        $this->assertCount(10, $posts);
    }

    /** @test */
    public function it_invokes_after_creating_and_making_callbacks()
    {
        $user = FactoryTestUserFactory::newFactory()
            ->afterMaking(function ($user) {
                $_SERVER['__test.user.making'] = $user;
            })
            ->afterCreating(function ($user) {
                $_SERVER['__test.user.creating'] = $user;
            })
            ->create();

        $this->assertSame($user, $_SERVER['__test.user.making']);
        $this->assertSame($user, $_SERVER['__test.user.creating']);

        unset($_SERVER['__test.user.making'], $_SERVER['__test.user.creating']);
    }

    /** @test */
    public function it_supports_has_many_relationships()
    {
        $users = FactoryTestUserFactory::times(10)
            ->has(
                FactoryTestPostFactory::times(3)
                    ->state(function ($attributes, $user) {
                        // Test parent is passed to child state mutations...
                        $_SERVER['__test.post.state-user'] = $user;

                        return [];
                    })
                    // Test parents passed to callback...
                    ->afterCreating(function ($post, $user) {
                        $_SERVER['__test.post.creating-post'] = $post;
                        $_SERVER['__test.post.creating-user'] = $user;
                    }),
                'posts'
            )
            ->create();

        $this->assertCount(10, FactoryTestUser::all());
        $this->assertCount(30, FactoryTestPost::all());
        $this->assertCount(3, FactoryTestUser::query()->latest()->first()->posts);

        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.post.creating-post']);
        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.post.creating-user']);
        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.post.state-user']);

        unset($_SERVER['__test.post.creating-post'], $_SERVER['__test.post.creating-user'], $_SERVER['__test.post.state-user']);
    }

    /** @test */
    public function it_supports_belongs_to_relationships()
    {
        $posts = FactoryTestPostFactory::times(3)
            ->for(FactoryTestUserFactory::newFactory(['name' => 'Taylor Otwell']), 'user')
            ->create();

        $this->assertCount(3, $posts->filter(function ($post) {
            return $post->user->name === 'Taylor Otwell';
        }));

        $this->assertCount(1, FactoryTestUser::all());
        $this->assertCount(3, FactoryTestPost::all());
    }

    /** @test */
    public function it_supports_belongs_to_relationships_with_existing_model_instances()
    {
        $user = FactoryTestUserFactory::newFactory(['name' => 'Taylor Otwell'])->create();
        $posts = FactoryTestPostFactory::times(3)
            ->for($user, 'user')
            ->create();

        $this->assertCount(3, $posts->filter(function ($post) use ($user) {
            return $post->user->getKey() == $user->getKey();
        }));

        $this->assertCount(1, FactoryTestUser::all());
        $this->assertCount(3, FactoryTestPost::all());
    }

    /** @test */
    public function it_supports_belongs_to_relationships_with_existing_model_instances_with_an_implied_relation_name()
    {
        $user = FactoryTestUserFactory::newFactory(['name' => 'Taylor Otwell'])->create();
        $posts = FactoryTestPostFactory::times(3)
            ->for($user)
            ->create();

        $this->assertCount(3, $posts->filter(function ($post) use ($user) {
            return $post->factoryTestUser->getKey() == $user->getKey();
        }));

        $this->assertCount(1, FactoryTestUser::all());
        $this->assertCount(3, FactoryTestPost::all());
    }

    /** @test */
    public function it_supports_morph_to_relationships()
    {
        $posts = FactoryTestCommentFactory::times(3)
            ->for(FactoryTestPostFactory::newFactory(['title' => 'Test Title']), 'commentable')
            ->create();

        $this->assertSame('Test Title', FactoryTestPost::query()->first()->title);
        $this->assertCount(3, FactoryTestPost::query()->first()->comments);

        $this->assertCount(1, FactoryTestPost::all());
        $this->assertCount(3, FactoryTestComment::all());
    }

    /** @test */
    public function it_supports_morph_to_relationships_with_existing_model_instances()
    {
        $post = FactoryTestPostFactory::newFactory(['title' => 'Test Title'])->create();
        $posts = FactoryTestCommentFactory::times(3)
            ->for($post, 'commentable')
            ->create();

        $this->assertSame('Test Title', FactoryTestPost::query()->first()->title);
        $this->assertCount(3, FactoryTestPost::query()->first()->comments);

        $this->assertCount(1, FactoryTestPost::all());
        $this->assertCount(3, FactoryTestComment::all());
    }

    /** @test */
    public function it_supports_belongs_to_many_relationships()
    {
        $users = FactoryTestUserFactory::times(3)
            ->hasAttached(
                FactoryTestRoleFactory::times(3)->afterCreating(function ($role, $user) {
                    $_SERVER['__test.role.creating-role'] = $role;
                    $_SERVER['__test.role.creating-user'] = $user;
                }),
                ['admin' => 'Y'],
                'roles'
            )
            ->create();

        $this->assertCount(9, FactoryTestRole::all());

        $user = FactoryTestUser::query()->latest()->first();

        $this->assertCount(3, $user->roles);
        $this->assertSame('Y', $user->roles->first()->pivot->admin);

        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.role.creating-role']);
        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.role.creating-user']);

        unset($_SERVER['__test.role.creating-role'], $_SERVER['__test.role.creating-user']);
    }

    /** @test */
    public function it_supports_belongs_to_many_relationships_with_existing_model_instances()
    {
        $roles = FactoryTestRoleFactory::times(3)
            ->afterCreating(function ($role) {
                $_SERVER['__test.role.creating-role'] = $role;
            })
            ->create();
        FactoryTestUserFactory::times(3)
            ->hasAttached($roles, ['admin' => 'Y'], 'roles')
            ->create();

        $this->assertCount(3, FactoryTestRole::all());

        $user = FactoryTestUser::query()->latest()->first();

        $this->assertCount(3, $user->roles);
        $this->assertSame('Y', $user->roles->first()->pivot->admin);

        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.role.creating-role']);

        unset($_SERVER['__test.role.creating-role']);
    }

    /** @test */
    public function it_supports_belongs_to_many_relationships_with_existing_model_instances_with_implied_relationship_name()
    {
        $roles = FactoryTestRoleFactory::times(3)
            ->afterCreating(function ($role) {
                $_SERVER['__test.role.creating-role'] = $role;
            })
            ->create();
        FactoryTestUserFactory::times(3)
            ->hasAttached($roles, ['admin' => 'Y'])
            ->create();

        $this->assertCount(3, FactoryTestRole::all());

        $user = FactoryTestUser::query()->latest()->first();

        $this->assertCount(3, $user->factoryTestRoles);
        $this->assertSame('Y', $user->factoryTestRoles->first()->pivot->admin);

        $this->assertInstanceOf(Eloquent::class, $_SERVER['__test.role.creating-role']);

        unset($_SERVER['__test.role.creating-role']);
    }

    /** @test */
    public function it_supports_sequences()
    {
        $users = FactoryTestUserFactory::times(2)->sequence(
            ['name' => 'Taylor Otwell'],
            ['name' => 'Abigail Otwell']
        )->create();

        $this->assertSame('Taylor Otwell', $users[0]->name);
        $this->assertSame('Abigail Otwell', $users[1]->name);

        $user = FactoryTestUserFactory::newFactory()
            ->hasAttached(
                FactoryTestRoleFactory::times(4),
                new Sequence(['admin' => 'Y'], ['admin' => 'N']),
                'roles'
            )
            ->create();

        $this->assertCount(4, $user->roles);

        $this->assertCount(2, $user->roles->filter(function ($role) {
            return $role->pivot->admin === 'Y';
        }));

        $this->assertCount(2, $user->roles->filter(function ($role) {
            return $role->pivot->admin === 'N';
        }));

        $users = FactoryTestUserFactory::times(2)->sequence(function ($sequence) {
            return ['name' => 'index: '.$sequence->index];
        })->create();

        $this->assertSame('index: 0', $users[0]->name);
        $this->assertSame('index: 1', $users[1]->name);
    }

    /** @test */
    public function it_supports_cross_join_sequences()
    {
        $assert = function ($users) {
            $assertions = [
                ['first_name' => 'Thomas', 'last_name' => 'Anderson'],
                ['first_name' => 'Thomas', 'last_name' => 'Smith'],
                ['first_name' => 'Agent', 'last_name' => 'Anderson'],
                ['first_name' => 'Agent', 'last_name' => 'Smith'],
            ];

            foreach ($assertions as $key => $assertion) {
                $this->assertSame($assertion, [
                    'first_name' => $users[$key]->first_name,
                    'last_name' => $users[$key]->last_name,
                ]);
            }
        };

        $usersByClass = FactoryTestUserFactory::times(4)
            ->state(
                new CrossJoinSequence(
                    [['first_name' => 'Thomas'], ['first_name' => 'Agent']],
                    [['last_name' => 'Anderson'], ['last_name' => 'Smith']]
                )
            )
            ->make();

        $assert($usersByClass);

        $usersByMethod = FactoryTestUserFactory::times(4)
            ->crossJoinSequence(
                [['first_name' => 'Thomas'], ['first_name' => 'Agent']],
                [['last_name' => 'Anderson'], ['last_name' => 'Smith']]
            )
            ->make();

        $assert($usersByMethod);
    }

    /** @test */
    public function it_resolves_nested_model_factories()
    {
        Factory::useNamespace('Factories\\');

        $resolves = [
            'App\\Foo' => 'Factories\\FooFactory',
            'App\\Models\\Foo' => 'Factories\\FooFactory',
            'App\\Models\\Nested\\Foo' => 'Factories\\Nested\\FooFactory',
            'App\\Models\\Really\\Nested\\Foo' => 'Factories\\Really\\Nested\\FooFactory',
        ];

        foreach ($resolves as $model => $factory) {
            $this->assertEquals($factory, Factory::resolveFactoryName($model));
        }
    }

    /** @test */
    public function it_resolves_nested_model_names_from_factories()
    {
        Container::getInstance()->instance(Application::class, $app = Mockery::mock(Application::class));
        $app->shouldReceive('getNamespace')->andReturn('Illuminate\\Tests\\Database\\Fixtures\\');

        Factory::useNamespace('Illuminate\\Tests\\Database\\Fixtures\\Factories\\');

        $factory = Price::factory();

        $this->assertSame(Price::class, $factory->modelName());
    }

    /** @test */
    public function it_resolves_non_app_nested_model_factories()
    {
        Container::getInstance()->instance(Application::class, $app = Mockery::mock(Application::class));
        $app->shouldReceive('getNamespace')->andReturn('Foo\\');

        Factory::useNamespace('Factories\\');

        $resolves = [
            'Foo\\Bar' => 'Factories\\BarFactory',
            'Foo\\Models\\Bar' => 'Factories\\BarFactory',
            'Foo\\Models\\Nested\\Bar' => 'Factories\\Nested\\BarFactory',
            'Foo\\Models\\Really\\Nested\\Bar' => 'Factories\\Really\\Nested\\BarFactory',
        ];

        foreach ($resolves as $model => $factory) {
            $this->assertEquals($factory, Factory::resolveFactoryName($model));
        }
    }

    /** @test */
    public function it_allows_models_to_have_factories()
    {
        Factory::guessFactoryNamesUsing(function ($model) {
            return $model.'Factory';
        });

        $this->assertInstanceOf(FactoryTestUserFactory::class, FactoryTestUser::factory());
    }

    /** @test */
    public function it_supports_dynamic_has_and_for_methods()
    {
        Factory::guessFactoryNamesUsing(function ($model) {
            return $model.'Factory';
        });

        $user = FactoryTestUserFactory::newFactory()->hasPosts(3)->create();

        $this->assertCount(3, $user->posts);

        $post = FactoryTestPostFactory::newFactory()
            ->forAuthor(['name' => 'Taylor Otwell'])
            ->hasComments(2)
            ->create();

        $this->assertInstanceOf(FactoryTestUser::class, $post->author);
        $this->assertSame('Taylor Otwell', $post->author->name);
        $this->assertCount(2, $post->comments);
    }

    /** @test */
    public function it_is_macroable()
    {
        $factory = FactoryTestUserFactory::newFactory();
        $factory->macro('getFoo', function () {
            return 'Hello World';
        });

        $this->assertSame('Hello World', $factory->getFoo());
    }

    /** @test */
    public function it_can_conditionally_execute_code()
    {
        FactoryTestUserFactory::newFactory()
            ->when(true, function () {
                $this->assertTrue(true);
            })
            ->when(false, function () {
                $this->fail('Unreachable code that has somehow been reached.');
            })
            ->unless(false, function () {
                $this->assertTrue(true);
            })
            ->unless(true, function () {
                $this->fail('Unreachable code that has somehow been reached.');
            });
    }

    /** @return ConnectionInterface */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /** @return Builder */
    protected function schema()
    {
        $connection = $this->connection();
        assert($connection instanceof Connection);

        return $connection->getSchemaBuilder();
    }

    private function assertIsArray($value)
    {
        $this->assertTrue(is_array($value));
    }

    private function assertIsInt($value)
    {
        $this->assertTrue(is_int($value));
    }

    private function assertIsCallable($value)
    {
        $this->assertTrue(is_callable($value));
    }
}

class FactoryTestUserFactory extends Factory
{
    protected $model = FactoryTestUser::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'options' => null,
        ];
    }
}

class FactoryTestUser extends Eloquent
{
    use HasFactory;

    protected $table = 'users';

    public function posts()
    {
        return $this->hasMany(FactoryTestPost::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(FactoryTestRole::class, 'role_user', 'user_id', 'role_id')->withPivot('admin');
    }

    public function factoryTestRoles()
    {
        return $this->belongsToMany(FactoryTestRole::class, 'role_user', 'user_id', 'role_id')->withPivot('admin');
    }
}

class FactoryTestPostFactory extends Factory
{
    protected $model = FactoryTestPost::class;

    public function definition()
    {
        return [
            'user_id' => FactoryTestUserFactory::newFactory(),
            'title' => $this->faker->name,
        ];
    }
}

class FactoryTestPost extends Eloquent
{
    protected $table = 'posts';

    public function user()
    {
        return $this->belongsTo(FactoryTestUser::class, 'user_id');
    }

    public function factoryTestUser()
    {
        return $this->belongsTo(FactoryTestUser::class, 'user_id');
    }

    public function author()
    {
        return $this->belongsTo(FactoryTestUser::class, 'user_id');
    }

    public function comments()
    {
        return $this->morphMany(FactoryTestComment::class, 'commentable');
    }
}

class FactoryTestCommentFactory extends Factory
{
    protected $model = FactoryTestComment::class;

    public function definition()
    {
        return [
            'commentable_id' => FactoryTestPostFactory::newFactory(),
            'commentable_type' => FactoryTestPost::class,
            'body' => $this->faker->name,
        ];
    }
}

class FactoryTestComment extends Eloquent
{
    protected $table = 'comments';

    public function commentable()
    {
        return $this->morphTo();
    }
}

class FactoryTestRoleFactory extends Factory
{
    protected $model = FactoryTestRole::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}

class FactoryTestRole extends Eloquent
{
    protected $table = 'roles';

    public function users()
    {
        return $this->belongsToMany(FactoryTestUser::class, 'role_user', 'role_id', 'user_id')->withPivot('admin');
    }
}
