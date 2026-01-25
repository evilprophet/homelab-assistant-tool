# Future Refactoring Proposal - Plan (Minimalistic)

**Status:** Proposal for Future Implementation  
**Priority:** Low  
**Estimated Time:** 3h  
**Expected Coverage Gain:** +5% (83% → 87-88%)  
**Last Updated:** 2026-01-25

---

## 📋 Overview

This document describes a minimalistic refactoring approach to increase test coverage from the current **83%** to **87-88%** with minimal risk and reasonable time investment.

**Current State:**
- Coverage: 83.20% (312/375 lines)
- Untested: 63 lines (17%)
- Status: Production Ready

**After Plan B:**
- Coverage: ~87-88% (327-330/375 lines)
- Untested: ~45-48 lines (12-13%)
- Status: Still Production Ready

---

## 🎯 Scope - What Will Be Changed

### Files to Modify:

1. **src/Model/Device/Generic.php** - Add DI for NetworkService
2. **src/Service/NetworkService.php** - NEW file (wrapper for Ping & WOL)
3. **tests/Unit/Model/Device/GenericTest.php** - Add 2 tests
4. **tests/Unit/Service/NetworkServiceTest.php** - NEW file (3-5 tests)
5. **tests/Unit/Provider/UpsProviderTest.php** - Add 3 tests
6. **tests/Unit/Provider/DeviceProviderTest.php** - Add 1 test

**Total:** 2 new files, 4 modified files

---

## 🔧 Detailed Changes

### 1. NetworkService (NEW) - Wrapper for External Dependencies

#### Purpose:
Isolate external dependencies (Ping, WOL) to make Generic class testable.

#### Implementation:

**File:** `src/Service/NetworkService.php`

```php
<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service;

use Geerlingguy\Ping\Ping;
use WakeOnLan\WakeOnLan;

/**
 * Service wrapper for network operations (Ping, WOL).
 * Allows mocking in tests.
 */
class NetworkService
{
    /**
     * Check if host is reachable via ping.
     *
     * @param string $ip IP address to ping
     * @return bool True if host is reachable, false otherwise
     */
    public function ping(string $ip): bool
    {
        $ping = new Ping($ip);
        $latency = $ping->ping();
        
        return $latency !== false;
    }

    /**
     * Send Wake-on-LAN magic packet to MAC address.
     *
     * @param string $mac MAC address in format XX:XX:XX:XX:XX:XX
     * @return bool Always returns true (WOL is fire-and-forget)
     */
    public function wakeOnLan(string $mac): bool
    {
        $wol = new WakeOnLan();
        $wol->wake($mac);
        
        return true;
    }
}
```

**Lines:** ~30  
**Dependencies:** Geerlingguy\Ping, WakeOnLan (already in project)  
**Testing:** Easy to mock

---

### 2. Generic Class Refactoring - Add Optional DI

#### Current Code:

```php
// src/Model/Device/Generic.php
class Generic implements DeviceInterface
{
    public function __construct(
        protected Configuration $configuration
    ) {
    }

    public function checkStatus(): bool
    {
        $ping = new Ping($this->ip);
        $latency = $ping->ping();

        $this->status = $latency !== false;

        return $this->status;
    }

    public function start(): bool
    {
        $wol = new WakeOnLan();
        $wol->wake($this->mac);

        return true;
    }
}
```

#### Proposed Changes:

```php
// src/Model/Device/Generic.php
use EvilStudio\HAT\Service\NetworkService;

class Generic implements DeviceInterface
{
    public function __construct(
        protected Configuration $configuration,
        protected ?NetworkService $networkService = null // ⭐ NEW - Optional DI
    ) {
        // Auto-instantiate if not provided (backward compatibility)
        $this->networkService ??= new NetworkService();
    }

    public function checkStatus(): bool
    {
        // Use injected service instead of direct instantiation
        $this->status = $this->networkService->ping($this->ip);

        return $this->status;
    }

    public function start(): bool
    {
        // Use injected service instead of direct instantiation
        return $this->networkService->wakeOnLan($this->mac);
    }
}
```

**Changes:**
- ✅ Add optional `NetworkService` parameter to constructor
- ✅ Use `??=` for backward compatibility (auto-instantiate if null)
- ✅ Replace `new Ping()` with `$this->networkService->ping()`
- ✅ Replace `new WakeOnLan()` with `$this->networkService->wakeOnLan()`

**Impact:**
- ✅ **Backward compatible** - existing code works without changes
- ✅ **Testable** - can inject mock in tests
- ✅ **No breaking changes** - DeviceFactory doesn't need updates

**Coverage Gain:** +5 lines

---

### 3. DeviceFactory - No Changes Needed!

**Why?** Because we used optional DI with `??=`, DeviceFactory doesn't need any modifications:

```php
// src/Model/DeviceFactory.php - NO CHANGES NEEDED
public function createDevice(array $deviceData): DeviceInterface
{
    // ...
    case 'generic':
        return new Generic($this->configuration); // ✅ Still works!
    // ...
}
```

The `Generic` constructor will auto-instantiate `NetworkService` when not provided.

---

### 4. Tests for NetworkService (NEW)

**File:** `tests/Unit/Service/NetworkServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service;

use EvilStudio\HAT\Service\NetworkService;
use PHPUnit\Framework\TestCase;

/**
 * Note: These tests use real Ping/WOL libraries.
 * For true unit tests, we would need to mock those too,
 * but that's beyond the scope of this minimalistic refactoring.
 */
class NetworkServiceTest extends TestCase
{
    public function testConstructor(): void
    {
        $service = new NetworkService();
        
        $this->assertInstanceOf(NetworkService::class, $service);
    }

    public function testPingWithLocalhostReturnsTrue(): void
    {
        $service = new NetworkService();
        
        // Localhost should always be reachable
        $result = $service->ping('127.0.0.1');
        
        $this->assertTrue($result);
    }

    public function testWakeOnLanReturnsTrue(): void
    {
        $service = new NetworkService();
        
        // WOL is fire-and-forget, always returns true
        $result = $service->wakeOnLan('00:11:22:33:44:55');
        
        $this->assertTrue($result);
    }

    public function testPingWithInvalidIpReturnsFalse(): void
    {
        $service = new NetworkService();
        
        // 0.0.0.0 should not be reachable
        $result = $service->ping('0.0.0.0');
        
        $this->assertFalse($result);
    }
}
```

**Tests:** 4  
**Coverage:** NetworkService will have ~80% coverage  
**Note:** These are integration tests (use real Ping), but acceptable for this scope

---

### 5. Updated Generic Tests

**File:** `tests/Unit/Model/Device/GenericTest.php`

**Add these tests:**

```php
public function testCheckStatusUsesPingService(): void
{
    $networkServiceMock = $this->createMock(NetworkService::class);
    $networkServiceMock->expects($this->once())
        ->method('ping')
        ->with('192.168.1.10')
        ->willReturn(true);

    $generic = new Generic($this->configurationMock, $networkServiceMock);
    $generic->configure([
        'name' => 'Test Device',
        'ip' => '192.168.1.10',
        'mac' => '00:11:22:33:44:55',
        'platform' => 'generic',
    ]);

    $result = $generic->checkStatus();

    $this->assertTrue($result);
    $this->assertTrue($generic->getStatus());
}

public function testStartUsesWakeOnLanService(): void
{
    $networkServiceMock = $this->createMock(NetworkService::class);
    $networkServiceMock->expects($this->once())
        ->method('wakeOnLan')
        ->with('AA:BB:CC:DD:EE:FF')
        ->willReturn(true);

    $generic = new Generic($this->configurationMock, $networkServiceMock);
    $generic->configure([
        'name' => 'Test Device',
        'ip' => '192.168.1.20',
        'mac' => 'AA:BB:CC:DD:EE:FF',
        'platform' => 'generic',
    ]);

    $result = $generic->start();

    $this->assertTrue($result);
}
```

**New tests:** 2  
**Coverage gain:** +5 lines in Generic class

---

### 6. Updated Provider Tests

#### UpsProviderTest - Add method tests

```php
public function testUpdateAllUpsStatusCallsUpdateOnEachUps(): void
{
    $upsMock1 = $this->createMock(UpsInterface::class);
    $upsMock1->expects($this->once())->method('updateStatus');

    $upsMock2 = $this->createMock(UpsInterface::class);
    $upsMock2->expects($this->once())->method('updateStatus');

    // Use reflection to inject mocked UPS objects
    $provider = new UpsProvider($this->configuration, [
        ['identifier' => 'ups1', 'safe_battery_runtime_threshold' => 600],
        ['identifier' => 'ups2', 'safe_battery_runtime_threshold' => 300],
    ]);

    $reflection = new \ReflectionClass($provider);
    $property = $reflection->getProperty('upsList');
    $property->setAccessible(true);
    $property->setValue($provider, ['ups1' => $upsMock1, 'ups2' => $upsMock2]);

    $provider->updateAllUpsStatus();
}

public function testIsAnyUpsOnBatteryReturnsTrueWhenOneUpsOnBattery(): void
{
    $upsMock1 = $this->createMock(UpsInterface::class);
    $upsMock1->method('isOnBattery')->willReturn(false);

    $upsMock2 = $this->createMock(UpsInterface::class);
    $upsMock2->method('isOnBattery')->willReturn(true);

    $provider = new UpsProvider($this->configuration, [
        ['identifier' => 'ups1', 'safe_battery_runtime_threshold' => 600],
        ['identifier' => 'ups2', 'safe_battery_runtime_threshold' => 300],
    ]);

    // Inject mocks via reflection
    $reflection = new \ReflectionClass($provider);
    $property = $reflection->getProperty('upsList');
    $property->setAccessible(true);
    $property->setValue($provider, ['ups1' => $upsMock1, 'ups2' => $upsMock2]);

    $result = $provider->isAnyUpsOnBattery();

    $this->assertTrue($result);
}

public function testIsAnyUpsOnBatteryReturnsFalseWhenNoUpsOnBattery(): void
{
    $upsMock1 = $this->createMock(UpsInterface::class);
    $upsMock1->method('isOnBattery')->willReturn(false);

    $upsMock2 = $this->createMock(UpsInterface::class);
    $upsMock2->method('isOnBattery')->willReturn(false);

    $provider = new UpsProvider($this->configuration, [
        ['identifier' => 'ups1', 'safe_battery_runtime_threshold' => 600],
        ['identifier' => 'ups2', 'safe_battery_runtime_threshold' => 300],
    ]);

    // Inject mocks via reflection
    $reflection = new \ReflectionClass($provider);
    $property = $reflection->getProperty('upsList');
    $property->setAccessible(true);
    $property->setValue($provider, ['ups1' => $upsMock1, 'ups2' => $upsMock2]);

    $result = $provider->isAnyUpsOnBattery();

    $this->assertFalse($result);
}
```

**New tests:** 3  
**Coverage gain:** +8 lines

#### DeviceProviderTest - Add checkAllDevicesStatus test

```php
public function testCheckAllDevicesStatusCallsCheckStatusOnEachDevice(): void
{
    $deviceMock1 = $this->createMock(DeviceInterface::class);
    $deviceMock1->expects($this->once())->method('checkStatus');

    $deviceMock2 = $this->createMock(DeviceInterface::class);
    $deviceMock2->expects($this->once())->method('checkStatus');

    // Inject mocks via reflection
    $provider = new DeviceProvider(
        $this->configuration,
        $this->deviceFactory,
        []
    );

    $reflection = new \ReflectionClass($provider);
    $property = $reflection->getProperty('deviceList');
    $property->setAccessible(true);
    $property->setValue($provider, [
        'device1' => $deviceMock1,
        'device2' => $deviceMock2,
    ]);

    $provider->checkAllDevicesStatus();
}
```

**New tests:** 1  
**Coverage gain:** +4 lines

---

## 📊 Expected Results

### Coverage Improvement:

| Component | Current | After Plan B | Gain |
|-----------|---------|-------------|------|
| Generic | 88.10% (37/42) | 100% (42/42) | +5 lines |
| UpsProvider | 71.43% (15/21) | 90% (19/21) | +4 lines |
| DeviceProvider | 80.95% (17/21) | 95% (20/21) | +3 lines |
| NetworkService | - | 80% (24/30) | +0 (new) |
| **GLOBAL** | **83.20%** | **~87-88%** | **+12-15 lines** |

### Test Statistics:

| Metric | Current | After Plan B | Change |
|--------|---------|-------------|--------|
| Tests | 165 | 175-178 | +10-13 |
| Assertions | 423 | 450-460 | +27-37 |
| Test Files | 20 | 21 | +1 |
| Coverage | 83.20% | 87-88% | +4-5% |

---

## 🛠️ Implementation Steps

### Phase 1: Create NetworkService (30 min)
1. Create `src/Service/NetworkService.php`
2. Implement `ping()` method
3. Implement `wakeOnLan()` method
4. Add PHPDoc comments

### Phase 2: Refactor Generic (30 min)
1. Add optional `NetworkService` parameter to constructor
2. Add `??=` for backward compatibility
3. Replace `new Ping()` with service call
4. Replace `new WakeOnLan()` with service call
5. Verify no breaking changes

### Phase 3: Add Tests (1.5h)
1. Create `NetworkServiceTest.php` (4 tests)
2. Add 2 tests to `GenericTest.php`
3. Add 3 tests to `UpsProviderTest.php`
4. Add 1 test to `DeviceProviderTest.php`
5. Run all tests - verify passing

### Phase 4: Verification (30 min)
1. Run full test suite
2. Generate coverage report with Xdebug
3. Verify 87-88% coverage
4. Update documentation
5. Commit changes

---

## ⚠️ Risks & Considerations

### Low Risk:
- ✅ NetworkService is a simple wrapper
- ✅ Generic uses optional DI - backward compatible
- ✅ No changes to DeviceFactory needed
- ✅ All existing tests will pass

### Considerations:
- ⚠️ NetworkServiceTest uses real Ping (integration test)
- ⚠️ UpsProvider tests use Reflection (not ideal, but acceptable)
- ⚠️ Still ~12% uncovered (Linux::stop/ssh, SshIntoDevice, etc.)

### Not Included:
- ❌ Linux class (requires SshService - 2h extra)
- ❌ SshIntoDeviceCommand (requires ProcessWrapper - 1.5h extra)
- ❌ Full isolation of Ping/WOL (would need PHP function mocking)

---

## 🎯 Why Plan B and Not Full Refactoring?

### Plan B (This Document):
- **Time:** 3h
- **Coverage gain:** +5%
- **Risk:** Low
- **Breaking changes:** None
- **New code:** ~30 lines (NetworkService)
- **Refactored code:** 2 methods in Generic

### Full Refactoring (Opcja A):
- **Time:** 10h
- **Coverage gain:** +10%
- **Risk:** Medium
- **Breaking changes:** 6 classes
- **New code:** ~115 lines (4 services)
- **Refactored code:** 15+ methods

**Conclusion:** Plan B offers the best cost/benefit ratio.

---

## 📝 Future Extensions

If Plan B is successful and you want more coverage, consider:

1. **SshService** (2h) - Wraps SSH2 for Linux class
   - Coverage gain: +10 lines
   - Would cover Linux::stop()

2. **ProcessWrapper** (1.5h) - Wraps Symfony Process
   - Coverage gain: +11 lines
   - Would cover SshIntoDeviceCommand

3. **UpsCommandExecutor** (1h) - Wraps exec() for UPS
   - Coverage gain: +2 lines
   - Would fully cover Ups::updateStatus()

**Total for 95% coverage:** ~7.5h additional work

---

## ✅ Decision Checklist

Before implementing Plan B, consider:

- [ ] Is 83% coverage insufficient for your needs?
- [ ] Do you have 3h development time available?
- [ ] Is the +5% coverage gain worth the effort?
- [ ] Are you comfortable with optional DI pattern?
- [ ] Will the team maintain NetworkService going forward?

**If all YES:** Proceed with Plan B  
**If any NO:** Stay with current 83% coverage (recommended)

---

## 📚 References

- Current coverage report: `tests/COVERAGE_REPORT_FINAL.md`
- Full refactoring plan: `tests/OPTION_1_EASY_WINS_SUMMARY.md`
- Generic class: `src/Model/Device/Generic.php`
- Test documentation: `tests/README.md`

---

## 🎓 Lessons from Current Implementation

### What Worked Well:
- ✅ Optional DI with `??=` is backward compatible
- ✅ Mockowanie services ułatwia testowanie
- ✅ Small, focused wrappers są łatwe w utrzymaniu

### What to Avoid:
- ❌ Don't over-engineer - 83% is already excellent
- ❌ Don't use Reflection unless necessary
- ❌ Don't mock built-in PHP functions (uopz, etc.)

---

**Status:** PROPOSAL - Not Implemented  
**Priority:** LOW  
**Recommendation:** Stay with 83% coverage unless business requirement changes

**Document Version:** 1.0  
**Last Updated:** 2026-01-25  
**Author:** GitHub Copilot
