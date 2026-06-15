<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

final class TicketingSourceCatalog
{
  public const MODULE_MANUAL = 'manual';

  public const MODULE_PROJECT_ONE = 'project_one';

  public const MODULE_E_APPROVAL = 'e_approval';

  public const MODULE_SITES = 'sites';

  public const MODULE_TOWER_ONE = 'tower_one';

  public const MODULE_FIBER_ONE = 'fiber_one';

  public const MODULE_ASSET_ONE = 'asset_one';

  public const MODULE_GIS = 'gis';

  /**
   * @return list<string>
   */
  public function modules(): array
  {
    return [
      self::MODULE_MANUAL,
      self::MODULE_PROJECT_ONE,
      self::MODULE_E_APPROVAL,
      self::MODULE_SITES,
      self::MODULE_TOWER_ONE,
      self::MODULE_FIBER_ONE,
      self::MODULE_ASSET_ONE,
      self::MODULE_GIS,
    ];
  }

  /**
   * @return array<string, string>
   */
  public function labels(): array
  {
    return [
      self::MODULE_MANUAL => 'Manual',
      self::MODULE_PROJECT_ONE => 'Project-One',
      self::MODULE_E_APPROVAL => 'E-Approval',
      self::MODULE_SITES => 'Sites',
      self::MODULE_TOWER_ONE => 'Tower-One',
      self::MODULE_FIBER_ONE => 'Fiber-One',
      self::MODULE_ASSET_ONE => 'Asset-One',
      self::MODULE_GIS => 'GIS',
    ];
  }

  public function isKnownModule(string $module): bool
  {
    return in_array($module, $this->modules(), true);
  }
}
