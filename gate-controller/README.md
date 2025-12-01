## Gate Controller Service (Boom Gate Integration)

The recommended and supported approach in this project is now **100% Laravel‑based**
for boom gate control. No Python or external services are required.

The flow is:

1. **Operator** clicks the **"Open Gate"** button in the desktop app.
2. Frontend calls `GateControlService.manualControl({ gate_id, action: 'open' })`.
3. Laravel `GateControlService` writes the action into cache (`gate_control_{gateId}`
   and/or `gate_emergency_{gateId}`).
4. The `App\Services\GateHardwareService` reads these cached actions and forwards
   them to the configured `boom_gate` device in the `gate_devices` table.
5. The Artisan command `php artisan gate:process-hardware` runs in the background
   (or under Supervisor/systemd) and continuously processes new actions.

To use this:

- Configure a `boom_gate` device per gate in `gate_devices` with the correct
  IP/port/credentials.
- Optionally set the following environment variables to match your boom gate
  controller HTTP API paths:

```env
BOOM_GATE_OPEN_PATH=/open
BOOM_GATE_CLOSE_PATH=/close
BOOM_GATE_DENY_PATH=/deny
```

- Start the hardware processor:

```bash
php artisan gate:process-hardware
```

This way, **only Laravel** is responsible for coordinating logical gate decisions
and talking to the physical boom gate hardware.

