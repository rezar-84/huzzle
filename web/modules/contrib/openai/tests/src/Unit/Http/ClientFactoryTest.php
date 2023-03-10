<?php

namespace Drupal\Tests\openai\Unit\Http;

use Drupal\Core\Config\Config;
use Drupal\Tests\UnitTestCase;
use OpenAI\Client as OpenAIAPIClient;
use Drupal\openai\Http\ClientFactory;

/**
 * @coversDefaultClass \Drupal\openai\Http\ClientFactory
 * @group openai
 */
class ClientFactoryTest extends UnitTestCase {

  /**
   * The client factory under test.
   *
   * @var \Drupal\openai\Http\ClientFactory
   */
  protected ClientFactory $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $storage = $this->createMock('Drupal\Core\Config\StorageInterface');
    $event_dispatcher = $this->createMock('Symfony\Contracts\EventDispatcher\EventDispatcherInterface');
    $typed_config = $this->createMock('Drupal\Core\Config\TypedConfigManagerInterface');
    $settings = new Config('openai.settings', $storage, $event_dispatcher, $typed_config);
    $settings->initWithData(
      [
        'api_key' => 'foo',
        'api_org' => 'bar',
      ]
    );

    $config = $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->getMock();

    $config->expects($this->any())
      ->method('get')
      ->willReturn($settings);

    $this->factory = new ClientFactory($config);
  }

  /**
   * Test that our factory returns a valid instance of the OpenAI client.
   */
  public function testFactoryCreation(): void {
    $this->assertInstanceOf(OpenAIAPIClient::class, $this->factory->create());
  }

}
