<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\Plugin\Block;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\reqres_api_user_block\Data\User;
use Drupal\reqres_api_user_block\Plugin\Block\ReqResUsersBlock;
use Drupal\reqres_api_user_block\Service\UserProviderInterface;
use Drupal\reqres_api_user_block\Service\UserProviderResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\reqres_api_user_block\Plugin\Block\ReqResUsersBlock
 */
class ReqResUsersBlockTest extends TestCase
{
    private UserProviderInterface&MockObject $userProvider;
    private TranslationInterface&MockObject $stringTranslation;
    private RequestStack&MockObject $requestStack;
    private PagerManagerInterface&MockObject $pagerManager;
    private ReqResUsersBlock $block;

    protected function setUp(): void
    {
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->stringTranslation = $this->createMock(
            TranslationInterface::class,
        );
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->pagerManager = $this->createMock(PagerManagerInterface::class);

        $this->stringTranslation->method("translate")->willReturnArgument(0);

        // Mock request to return page 0 by default
        $request = new Request();
        $request->query->set("page", "0");
        $this->requestStack->method("getCurrentRequest")->willReturn($request);

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
            $this->userProvider,
            $this->requestStack,
            $this->pagerManager,
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
            $this->userProvider,
            $this->requestStack,
            $this->pagerManager,
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
        $users = [
            new User(
                1,
                "george.bluth@reqres.in",
                "George",
                "Bluth",
                "https://reqres.in/img/faces/1-image.jpg",
            ),
            new User(
                2,
                "janet.weaver@reqres.in",
                "Janet",
                "Weaver",
                "https://reqres.in/img/faces/2-image.jpg",
            ),
        ];

        $result = new UserProviderResult(1, 6, 12, 2, $users);

        $this->userProvider
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 6)
            ->willReturn($result);

        $this->pagerManager
            ->expects($this->once())
            ->method("createPager")
            ->with(12, 6);

        $result = $this->block->build();

        $this->assertArrayHasKey("content", $result);
        $this->assertArrayHasKey("#markup", $result["content"]);
        $markup = $result["content"]["#markup"];

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
            $this->userProvider,
            $this->requestStack,
            $this->pagerManager,
        );
        $block->setStringTranslation($this->stringTranslation);

        $users = [
            new User(
                1,
                "test@example.com",
                "Test",
                "User",
                "https://example.com/avatar.jpg",
            ),
        ];

        $result = new UserProviderResult(1, 12, 24, 2, $users);

        $this->userProvider
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 12)
            ->willReturn($result);

        $this->pagerManager
            ->expects($this->once())
            ->method("createPager")
            ->with(24, 12);

        $result = $block->build();
        $markup = $result["content"]["#markup"];

        $this->assertStringContainsString("Test", $markup);
        $this->assertStringContainsString("User", $markup);
        $this->assertStringContainsString("test@example.com", $markup);
    }

    /**
     * @covers ::build
     */
    public function testBuildWithNoUsers(): void
    {
        $emptyResult = new UserProviderResult(1, 6, 0, 0, []);

        $this->userProvider
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 6)
            ->willReturn($emptyResult);

        $this->pagerManager
            ->expects($this->once())
            ->method("createPager")
            ->with(0, 6);

        $result = $this->block->build();

        $this->assertArrayHasKey("content", $result);
        $this->assertArrayHasKey("#markup", $result["content"]);
        $markup = $result["content"]["#markup"];

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
        $users = [
            new User(
                1,
                'test<script>alert("xss")</script>@example.com',
                "Test<b>Bold</b>",
                "User&amp;",
                "https://example.com/avatar.jpg?param=<script>",
            ),
        ];

        $result = new UserProviderResult(1, 6, 1, 1, $users);

        $this->userProvider
            ->expects($this->once())
            ->method("getUsers")
            ->willReturn($result);

        $this->pagerManager
            ->expects($this->once())
            ->method("createPager")
            ->with(1, 6);

        $result = $this->block->build();
        $markup = $result["content"]["#markup"];

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
            $this->userProvider,
            $this->requestStack,
            $this->pagerManager,
        );
        $block->setStringTranslation($this->stringTranslation);

        $users = [
            new User(
                1,
                "test@example.com",
                "Test",
                "User",
                "https://example.com/avatar.jpg",
            ),
        ];

        $result = new UserProviderResult(1, 6, 1, 1, $users);

        $this->userProvider
            ->expects($this->once())
            ->method("getUsers")
            ->willReturn($result);

        $this->pagerManager
            ->expects($this->once())
            ->method("createPager")
            ->with(1, 6);

        $result = $block->build();
        $markup = $result["content"]["#markup"];

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
            "reqres_api_user_block.user_provider",
            $this->userProvider,
        );
        $container->set("request_stack", $this->requestStack);
        $container->set("pager.manager", $this->pagerManager);

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
            $this->userProvider,
            $this->requestStack,
            $this->pagerManager,
        );
        $block->setStringTranslation($this->stringTranslation);

        $users = [
            new User(
                1,
                "single@example.com",
                "Single",
                "User",
                "https://example.com/avatar.jpg",
            ),
        ];

        $result = new UserProviderResult(1, 1, 1, 1, $users);

        $this->userProvider
            ->expects($this->once())
            ->method("getUsers")
            ->with(1, 1)
            ->willReturn($result);

        $this->pagerManager
            ->expects($this->once())
            ->method("createPager")
            ->with(1, 1);

        $result = $block->build();
        $markup = $result["content"]["#markup"];

        $this->assertStringContainsString("Found 1 total users", $markup);
        $this->assertStringContainsString("showing page 1 of 1", $markup);
        $this->assertStringContainsString("Single", $markup);
    }
}
