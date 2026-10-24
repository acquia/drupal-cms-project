<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit\Controller;

use Drupal\modeler_api\Api;
use Drupal\modeler_api\Controller\ModelerApi;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the controller passes replay data through without mutation.
 *
 * The replay-data return path lets a model owner provide debug/replay data to
 * the modeler. That data may contain deduplication/reference markers (for
 * example "@ref"/"@prev") that the modeler frontend expands lazily at display
 * time. The Modeler API must therefore return the data verbatim and never
 * expand or transform it.
 */
#[CoversClass(ModelerApi::class)]
#[Group('modeler_api')]
class ModelerApiReplayDataPassThroughTest extends UnitTestCase {

  /**
   * A marker-bearing replay-data array used across the assertions.
   *
   * It deliberately contains deduplication/reference markers and nested
   * structures so the test fails if anything walks and rewrites the array.
   *
   * @return array
   *   The marker-bearing replay-data fixture.
   */
  protected function markerBearingReplayData(): array {
    return [
      'tokens' => [
        'node' => [
          'title' => 'Example',
          'author' => ['@ref' => 'user:1'],
        ],
        'previous' => ['@prev' => 'node:tokens'],
      ],
      'events' => [
        ['id' => 'a', 'data' => ['@ref' => 'shared:1']],
        ['id' => 'b', 'data' => ['@prev' => 'a:data']],
      ],
      '@ref' => 'top-level-marker',
    ];
  }

  /**
   * Builds a controller whose owner returns the given replay data.
   *
   * @param array $replayData
   *   The replay data the mocked owner returns for both replay methods.
   * @param string $requestContent
   *   The raw JSON request body to feed the controller.
   *
   * @return \Drupal\modeler_api\Controller\ModelerApi
   *   The controller under test.
   */
  protected function buildController(array $replayData, string $requestContent): ModelerApi {
    $owner = $this->createStub(ModelOwnerInterface::class);
    $owner->method('getReplayDataByComponent')->willReturn($replayData);
    $owner->method('pollTestJob')->willReturn($replayData);

    $ownerManager = $this->createStub(ModelOwnerPluginManager::class);
    $ownerManager->method('createInstance')->willReturn($owner);

    $modelerManager = $this->createStub(ModelerPluginManager::class);
    $api = $this->createStub(Api::class);

    $request = $this->createStub(Request::class);
    $request->method('getContent')->willReturn($requestContent);

    return new ModelerApi($request, $ownerManager, $modelerManager, $api);
  }

  /**
   * Tests loadReplayData() returns marker-bearing data unchanged.
   */
  public function testLoadReplayDataPassesMarkersThrough(): void {
    $replayData = $this->markerBearingReplayData();
    $request = json_encode([
      'modelId' => 'model_1',
      'componentId' => 'component_1',
    ]);
    $controller = $this->buildController($replayData, $request);

    $response = $controller->loadReplayData('test_owner');
    $decoded = json_decode($response->getContent(), TRUE);

    // The endpoint must return the exact structure the owner provided, with
    // the dedup/reference markers intact for the frontend to expand.
    $this->assertSame($replayData, $decoded);
    $this->assertArrayHasKey('@ref', $decoded);
    $this->assertSame('user:1', $decoded['tokens']['node']['author']['@ref']);
    $this->assertSame('a:data', $decoded['events'][1]['data']['@prev']);
  }

  /**
   * Tests testModel() poll path returns marker-bearing data unchanged.
   */
  public function testPollTestJobPassesMarkersThrough(): void {
    $replayData = $this->markerBearingReplayData();
    $request = json_encode(['jobId' => 'job_1']);
    $controller = $this->buildController($replayData, $request);

    $response = $controller->testModel('test_owner');
    $decoded = json_decode($response->getContent(), TRUE);

    // The poll result is the replay data and must survive verbatim.
    $this->assertSame($replayData, $decoded);
    $this->assertArrayHasKey('@ref', $decoded);
    $this->assertSame('shared:1', $decoded['events'][0]['data']['@ref']);
    $this->assertSame('node:tokens', $decoded['tokens']['previous']['@prev']);
  }

}
