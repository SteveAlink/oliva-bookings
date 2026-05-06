# Oliva Bookings for WonderCMS

By Steve Alink for Oliva Solutions.

Oliva Bookings is a simple no-payment booking plugin for WonderCMS. It reads the event/date list from [Oliva Events](https://github.com/SteveAlink/oliva-events) and lets visitors reserve one or more seats or spots for an event.

## Dependency

Oliva Bookings requires `oliva-events` to be installed in WonderCMS first. The plugin reads the `olivaEventsUnavailableDates` data structure used by Oliva Events:

```text
2026-05-12|Workshop,2026-05-13|Open day
```

or:

```text
2026-05-12|Workshop
2026-05-13|Open day
```

## Installation and Usage

1. Install `oliva-events`.
2. Upload the `oliva-bookings` folder to `plugins/oliva-bookings`.
3. Enable the plugin in WonderCMS.
4. Open Settings and use the `Oliva Bookings` tab.
5. Add `{{oliva-bookings}}` to a page, or switch placement mode to footer.

## Booking Storage

Bookings are stored in the WonderCMS database through the plugin config key `olivaBookingsData` as JSON. No payments are taken or processed.

## Download the plugin via
Make sure to have the Oliva Events plugin installed. It can be done via:  
```text
https://raw.githubusercontent.com/SteveAlink/oliva-events/main/wcms-modules.json
```
And next:
```text
https://raw.githubusercontent.com/SteveAlink/oliva-bookings/main/wcms-modules.json
```

## Versions
v0.1.0 06-05-2026 Initial version

v0.1.0 Initial version

- Requires Oliva Events
- Lists events from Oliva Events date data
- Shows available spots counter
- Allows visitors to reserve seats or spots
- Stores bookings in WonderCMS config/database storage
- Adds `en_US.ini`, `nl_NL.ini`, and `es_ES.ini`
