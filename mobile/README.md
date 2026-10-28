# TRA Tax Club mobile app

Flutter app for Android and iOS. Members use it to:

- learn, take quizzes and join events;
- browse opportunities, use the community and ask TRA;
- enter competitions and see their achievements;
- request mentorship and send feedback.

It talks to the same Laravel API as the website (`/api/v1`) and works in Kiswahili and English.

Officers keep using the Administration Portal on the website.

## Run it

You need [Flutter](https://docs.flutter.dev/get-started/install) 3.47 or newer.

- **Android:** Android Studio, or the Android SDK on its own.
- **iPhone:** a Mac with Xcode.

```bash
cd mobile
flutter pub get
flutter run                                   # uses https://club.flux.co.tz
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000   # a local backend
```

When the backend runs on your computer, start it with `php artisan serve --host=0.0.0.0`. Then use your computer's network address, not `localhost`: a phone or emulator cannot reach the computer through `localhost`.

## Install a test build on an Android phone

**Option 1: build it yourself**

```bash
flutter build apk --release
```

Copy `build/app/outputs/flutter-apk/app-release.apk` to the phone and open it. Allow "Install unknown apps" when the phone asks.

**Option 2: download it from GitHub**

Every push that changes `mobile/` runs the **Mobile app** workflow. Open the run in the Actions tab and download the `tax-club-android-apk` artifact.

## Install on an iPhone

Apple only allows signed apps on iPhones:

1. On a Mac, open `ios/Runner.xcworkspace` in Xcode.
2. Under *Signing & Capabilities*, choose your Apple ID team. Use a unique Bundle Identifier if `tz.co.flux.taxClub` is taken.
3. Connect the iPhone and press Run.

A free Apple ID works for 7 days on your own phone. To share builds with testers, use TestFlight, which needs an Apple Developer Program account.

## Publish

**Google Play**

1. Create a keystore:
   ```bash
   keytool -genkey -v -keystore taxclub.jks -keyalg RSA -keysize 2048 -validity 10000 -alias taxclub
   ```
2. Create `android/key.properties`. It is not committed.
   ```
   storeFile=/full/path/taxclub.jks
   storePassword=...
   keyAlias=taxclub
   keyPassword=...
   ```
3. Run `flutter build appbundle` and upload `build/app/outputs/bundle/release/app-release.aab`.

**App Store**

```bash
flutter build ipa
```

Upload the result with Xcode or Transporter.

Raise `version:` in `pubspec.yaml` for every release.

To make older app versions ask members to update, set these on the server:

- `MOBILE_MIN_VERSION` and `MOBILE_LATEST_VERSION`;
- the store links in `MOBILE_ANDROID_URL` and `MOBILE_IOS_URL`.

## How it is built

| Folder | What |
|---|---|
| `lib/core/api.dart` | Dio client for the API envelope `{success, message, data, meta, errors}`. It sends the token and `Accept-Language`. |
| `lib/core/session.dart` | Sign-in (including the SMS code for staff), the token in secure storage, the language and the server's labels (`GET /app/config`). |
| `lib/core/i18n.dart`, `assets/i18n/sw.json` | Kiswahili keyed by the English text, like the website's `lang/sw.json`. |
| `lib/router.dart` | Routes and the five tabs: Home, Learn, Events, Community, More. |
| `lib/features/*` | One folder per area: learning, quizzes, events, opportunities, community, Ask TRA, competitions, achievements, alumni and mentorship, feedback, notifications, profile. |

### Adding text

Write the English text with `context.tr('...')`, then add the Kiswahili to `assets/i18n/sw.json`.

### Tests

```bash
flutter analyze
flutter test
```

### Not in this version

- **Push notifications:** these need a Firebase project. Members get SMS and the in-app inbox for now.
- **Scanning QR codes:** officers check members in from the Administration Portal. Members can check a card or certificate by its number in the app.
- **Alumni features:** alumni profiles, connections and stories are on the website.
