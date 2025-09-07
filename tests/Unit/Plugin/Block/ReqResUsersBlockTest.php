<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\Plugin\Block;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\reqres_api_user_block\Plugin\Block\ReqResUsersBlock;
use Drupal\reqres_api_user_block\Service\ReqResApiService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\reqres_api_user_block\Plugin\Block\ReqResUsersBlock
 */
class ReqResUsersBlockTest extends TestCase
{
    private ReqResApiService&MockObject $apiService;
    private TranslationInterface&MockObject $stringTranslation;
    private ReqResUsersBlock $block;

    protected function setUp(): void
    {
        $this->apiService = $this->createMock(ReqResApiService::class);
        $this->stringTranslation = $this->createMock(
            TranslationInterface::class,
        );

        $this->stringTranslation->method("translate")->willReturnArgument(0);

        $configuration = [
            "items_per_page" => 6,
            "email_label" => "Email",
            "forename_label" => "First Name",
            "surname_label" => "Last Name",
        ];
        $plugin_id = "reqres_users_block";
        $plugin_definition = [];

        $this->block = new ReqResUsersBlock(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $this->apiService,
        );

        $this->block->setStringTranslation($this->stringTranslation);
    }

    /**
     * @covers ::defaultConfiguration
     */
    public function testDefaultConfiguration(): void
    {
        $expected = [
            "items_per_page" => 6,
            "email_label" => "Email",
            "forename_label" => "First Name",
            "surname_label" => "Last Name",
        ];

        $config = $this->block->defaultConfiguration();

        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $config[$key]);
        }
    }

    /**
     * @covers ::blockForm
     */
    public function testBlockForm(): void
    {
        $form = [];
        $form_state = $this->createMock(FormStateInterface::class);

        $result = $this->block->blockForm($form, $form_state);

        // Check items_per_page field
        $this->assertEquals("number", $result["items_per_page"]["#type"]);
        $this->assertEquals(6, $result["items_per_page"]["#default_value"]);
        $this->assertEquals(1, $result["items_per_page"]["#min"]);
        $this->assertEquals(50, $result["items_per_page"]["#max"]);
        $this->assertTrue($result["items_per_page"]["#required"]);

        // Check email_label field
        $this->assertEquals("textfield", $result["email_label"]["#type"]);
        $this->assertEquals("Email", $result["email_label"]["#default_value"]);
        $this->assertTrue($result["email_label"]["#required"]);
        $this->assertEquals(255, $result["email_label"]["#maxlength"]);

        // Check forename_label field
        $this->assertEquals("textfield", $result["forename_label"]["#type"]);
        $this->assertEquals(
            "First Name",
            $result["forename_label"]["#default_value"],
        );
        $this->assertTrue($result["forename_label"]["#required"]);

        // Check surname_label field
        $this->assertEquals("textfield", $result["surname_label"]["#type"]);
        $this->assertEquals(
            "Last Name",
            $result["surname_label"]["#default_value"],
        );
        $this->assertTrue($result["surname_label"]["#required"]);
    }

    /**
     * @covers ::blockForm
     */
    public function testBlockFormWithCustomConfig(): void
    {
        $custom_config = [
            "items_per_page" => 12,
            "email_label" => "Email Address",
            "forename_label" => "Given Name",
            "surname_label" => "Family Name",
        ];

        $block = new ReqResUsersBlock(
            $custom_config,
            "reqres_users_block",
            [],
            $this->apiService,
        );
        $block->setStringTranslation($this->stringTranslation);

        $form = [];
        $form_state = $this->createMock(FormStateInterface::class);

        $result = $block->blockForm($form, $form_state);

        $this->assertEquals(12, $result["items_per_page"]["#default_value"]);
        $this->assertEquals(
            "Email Address",
            $result["email_label"]["#default_value"],
        );
        $this->assertEquals(
            "Given Name",
            $result["forename_label"]["#default_value"],
        );
        $this->assertEquals(
            "Family Name",
            $result["surname_label"]["#default_value"],
        );
    }

    /**
     * @covers ::blockSubmit
     */
    public function testBlockSubmit(): void
    {
        $form = [];
        $form_state = $this->createMock(FormStateInterface::class);

        $form_state
            ->expects($this->exactly(4))
            ->method("getValue")
            ->willReturnMap([
                ["items_per_page", null, "10"],
                ["email_label", null, "Email Address"],
                ["forename_label", null, "Given Name"],
                ["surname_label", null, "Family Name"],
            ]);

        $this->block->blockSubmit($form, $form_state);

        $config = $this->block->getConfiguration();
        $this->assertEquals(10, $config["items_per_page"]);
        $this->assertEquals("Email Address", $config["email_label"]);
        $this->assertEquals("Given Name", $config["forename_label"]);
        $this->assertEquals("Family Name", $config["surname_label"]);
    }

    /**
     * @covers ::build
     */
    public function testBuildWithUsers(): void
    {
        $users_data = [
            "page" => 1,
            "per_page" => 6,
            "total" => 12,
            "total_pages" => 2,
            "data" => [
                [
                    "id" => 1,
                    "email" => "george.bluth@reqres.in",
                    "first_name" => "George",
                    "last_name" => "Bluth",
                    "avatar" => "https://reqres.in/img/faces/1-image.jpg",
                ],
                [
                    "id" => 2,
                    "email" => "janet.weaver@reqres.in",
                    "first_name" => "Janet",
                    "last_name" => "Weaver",
                    "avatar" => "https://reqres.in/img/faces/2-image.jpg",
                ],
            ],
        ];

        $this->apiService
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 6)
            ->willReturn($users_data);

        $result = $this->block->build();

        $this->assertArrayHasKey("#markup", $result);
        $markup = $result["#markup"];

        $this->assertStringContainsString("<ul>", $markup);
        $this->assertStringContainsString("<li>", $markup);

        $this->assertStringContainsString("George", $markup);
        $this->assertStringContainsString("Bluth", $markup);
        $this->assertStringContainsString("george.bluth@reqres.in", $markup);
        $this->assertStringContainsString("Janet", $markup);
        $this->assertStringContainsString("Weaver", $markup);
        $this->assertStringContainsString("janet.weaver@reqres.in", $markup);

        $this->assertStringContainsString("Found 12 total users", $markup);
        $this->assertStringContainsString("showing page 1 of 2", $markup);

        $this->assertStringContainsString(
            "https://reqres.in/img/faces/1-image.jpg",
            $markup,
        );
        $this->assertStringContainsString(
            "https://reqres.in/img/faces/2-image.jpg",
            $markup,
        );
    }

    /**
     * @covers ::build
     */
    public function testBuildWithCustomConfig(): void
    {
        $custom_config = [
            "items_per_page" => 12,
            "email_label" => "Email Address",
            "forename_label" => "Given Name",
            "surname_label" => "Family Name",
        ];

        $block = new ReqResUsersBlock(
            $custom_config,
            "reqres_users_block",
            [],
            $this->apiService,
        );
        $block->setStringTranslation($this->stringTranslation);

        $users_data = [
            "page" => 1,
            "per_page" => 12,
            "total" => 24,
            "total_pages" => 2,
            "data" => [
                [
                    "id" => 1,
                    "email" => "test@example.com",
                    "first_name" => "Test",
                    "last_name" => "User",
                    "avatar" => "https://example.com/avatar.jpg",
                ],
            ],
        ];

        $this->apiService
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 12)
            ->willReturn($users_data);

        $result = $block->build();
        $markup = $result["#markup"];

        $this->assertStringContainsString("Test", $markup);
        $this->assertStringContainsString("User", $markup);
        $this->assertStringContainsString("test@example.com", $markup);
    }

    /**
     * @covers ::build
     */
    public function testBuildWithNoUsers(): void
    {
        $empty_data = [
            "page" => 1,
            "per_page" => 6,
            "total" => 0,
            "total_pages" => 0,
            "data" => [],
        ];

        $this->apiService
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 6)
            ->willReturn($empty_data);

        $result = $this->block->build();

        $this->assertArrayHasKey("#markup", $result);
        $markup = $result["#markup"];

        $this->assertStringContainsString(
            "No users found or API unavailable.",
            $markup,
        );
        $this->assertStringNotContainsString("<ul>", $markup);
    }

    /**
     * @covers ::build
     */
    public function testBuildHtmlEscaping(): void
    {
        $users_data = [
            "page" => 1,
            "per_page" => 6,
            "total" => 1,
            "total_pages" => 1,
            "data" => [
                [
                    "id" => 1,
                    "email" => 'test<script>alert("xss")</script>@example.com',
                    "first_name" => "Test<b>Bold</b>",
                    "last_name" => "User&amp;",
                    "avatar" => "https://example.com/avatar.jpg?param=<script>",
                ],
            ],
        ];

        $this->apiService
            ->expects($this->once())
            ->method("getUsers")
            ->willReturn($users_data);

        $result = $this->block->build();
        $markup = $result["#markup"];

        $this->assertStringContainsString(
            "Test&lt;b&gt;Bold&lt;/b&gt;",
            $markup,
        );
        $this->assertStringContainsString("User&amp;amp;", $markup);
        $this->assertStringContainsString(
            "test&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;@example.com",
            $markup,
        );
        $this->assertStringContainsString(
            "https://example.com/avatar.jpg?param=&lt;script&gt;",
            $markup,
        );

        $this->assertStringNotContainsString(
            '<script>alert("xss")</script>',
            $markup,
        );
        $this->assertStringNotContainsString("<b>Bold</b>", $markup);
    }

    /**
     * @covers ::build
     */
    public function testBuildConfigLabelEscaping(): void
    {
        $malicious_config = [
            "items_per_page" => 6,
            "email_label" => 'Email<script>alert("xss")</script>',
            "forename_label" => "First<b>Name</b>",
            "surname_label" => "Last&Name",
        ];

        $block = new ReqResUsersBlock(
            $malicious_config,
            "reqres_users_block",
            [],
            $this->apiService,
        );
        $block->setStringTranslation($this->stringTranslation);

        $users_data = [
            "page" => 1,
            "per_page" => 6,
            "total" => 1,
            "total_pages" => 1,
            "data" => [
                [
                    "id" => 1,
                    "email" => "test@example.com",
                    "first_name" => "Test",
                    "last_name" => "User",
                    "avatar" => "https://example.com/avatar.jpg",
                ],
            ],
        ];

        $this->apiService
            ->expects($this->once())
            ->method("getUsers")
            ->willReturn($users_data);

        $result = $block->build();
        $markup = $result["#markup"];

        $this->assertStringContainsString("Test", $markup);
        $this->assertStringContainsString("User", $markup);
        $this->assertStringContainsString("test@example.com", $markup);
    }

    /**
     * @covers ::create
     */
    public function testCreate(): void
    {
        $container = new ContainerBuilder();
        $container->set(
            "reqres_api_user_block.reqres_api_service",
            $this->apiService,
        );

        $configuration = [];
        $plugin_id = "reqres_users_block";
        $plugin_definition = [];

        $instance = ReqResUsersBlock::create(
            $container,
            $configuration,
            $plugin_id,
            $plugin_definition,
        );

        $this->assertInstanceOf(ReqResUsersBlock::class, $instance);
    }

    /**
     * @covers ::blockSubmit
     */
    public function testBlockSubmitIntegerConversion(): void
    {
        $form = [];
        $form_state = $this->createMock(FormStateInterface::class);

        $form_state
            ->expects($this->exactly(4))
            ->method("getValue")
            ->willReturnMap([
                ["items_per_page", null, "25"], // String that should be converted to int
                ["email_label", null, "Email"],
                ["forename_label", null, "First Name"],
                ["surname_label", null, "Last Name"],
            ]);

        $this->block->blockSubmit($form, $form_state);

        $config = $this->block->getConfiguration();
        $this->assertSame(25, $config["items_per_page"]);
        $this->assertTrue(is_int($config["items_per_page"]));
    }

    /**
     * @covers ::build
     */
    public function testBuildWithEdgeCasePagination(): void
    {
        $config = [
            "items_per_page" => 1,
            "email_label" => "Email",
            "forename_label" => "First Name",
            "surname_label" => "Last Name",
        ];

        $block = new ReqResUsersBlock(
            $config,
            "reqres_users_block",
            [],
            $this->apiService,
        );
        $block->setStringTranslation($this->stringTranslation);

        $users_data = [
            "page" => 1,
            "per_page" => 1,
            "total" => 1,
            "total_pages" => 1,
            "data" => [
                [
                    "id" => 1,
                    "email" => "single@example.com",
                    "first_name" => "Single",
                    "last_name" => "User",
                    "avatar" => "https://example.com/avatar.jpg",
                ],
            ],
        ];

        $this->apiService
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 1)
            ->willReturn($users_data);

        $result = $block->build();
        $markup = $result["#markup"];

        $this->assertStringContainsString("Found 1 total users", $markup);
        $this->assertStringContainsString("showing page 1 of 1", $markup);
        $this->assertStringContainsString("Single", $markup);
    }
}
