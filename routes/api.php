<?php

use App\Http\Controllers\API\Actuators\ActuatorsController;
use App\Http\Controllers\API\Actuators\UserActuatorsController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\AutomationsController;
use App\Http\Controllers\API\Billing\PaymentsController;
use App\Http\Controllers\API\Boards\BoardsController;
use App\Http\Controllers\API\Boards\SmartMeterController;
use App\Http\Controllers\API\Boards\UserBoardsController;
use App\Http\Controllers\API\DocumentsController;
use App\Http\Controllers\API\MessagesController;
use App\Http\Controllers\API\NotificationsController;
use App\Http\Controllers\API\PinTypesController;
use App\Http\Controllers\API\PreferencesController;
use App\Http\Controllers\API\Reports\BasicReportsController;
use App\Http\Controllers\API\Reports\SmartReportsController;
use App\Http\Controllers\API\ResourcesController;
use App\Http\Controllers\API\SeedController;
use App\Http\Controllers\API\Sensors\SensorsController;
use App\Http\Controllers\API\Sensors\UserSensorsController;
use App\Http\Controllers\API\ServiceDocumentsController;
use App\Http\Controllers\API\Settings\PagesController;
use App\Http\Controllers\API\Settings\PermissionsController;
use App\Http\Controllers\API\Settings\RolesController;
use App\Http\Controllers\API\SmartSchedulerController;
use App\Http\Controllers\API\TicketsController;
use App\Http\Controllers\API\UsersController;
use App\Http\Middleware\API\JWTAuth;
use App\Http\Middleware\API\PagesPermissions;
use App\Http\Middleware\Token;
use App\Models\Message;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('migrate', function() {
    // Artisan::call('migrate');
    // Artisan::call('db:seed');
    return response()->json(Artisan::output());
});

Route::get('link', function() {
    Artisan::call('storage:link');
    return response()->json(Artisan::output());
});

/**
 * Microcontroller Routes
 * Doesnt require auth
 */
Route::group([
    'middleware' => [ Token::class ]
], function () {
    Route::get('get-schedulers', [SmartSchedulerController::class, 'index']);
    Route::get('get-board-omc', [UserBoardsController::class, 'getBoardOMC']);
    Route::get('get-connections-omc', [UserBoardsController::class, 'getConnectionsOMC']);
    Route::get('get-actuators-omc', [UserBoardsController::class, 'getActuatorsOMC']);
    Route::get('get-sensors-omc', [UserBoardsController::class, 'getSensorsOMC']);
    Route::post('set-board-omc', [UserBoardsController::class, 'setBoardOMC']);
    Route::post('set-temper', [UserBoardsController::class, 'setTemper']);
    Route::post('set-meter-status', [UserBoardsController::class, 'setMeterStatus']);
    Route::get('set-sensor-data-omc', [UserSensorsController::class, 'setSensorData']);
    Route::get('get-actuator-status-omc/{userActuatorId}', [UserBoardsController::class, 'getActuatorStatus']);

    Route::post('store-power-data', [SmartMeterController::class, 'storePowerData']);
    Route::post('store-plug-data', [SmartMeterController::class, 'storePlugData']);
    Route::post('store-current-loss-data', [SmartMeterController::class, 'storeCurrentLossData']);
    Route::post('store-resistance-loss-data', [SmartMeterController::class, 'storeResistanceLossData']);
    Route::get('get-plug-power-status', [SmartMeterController::class, 'getPlugPowerStatus']);
    Route::get('get-meter-summary', [SmartReportsController::class, 'getUserBriefStats']);
});


Route::group([
    'middleware' => ['api']
], function () {

    // Auth API's
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth', [AuthController::class, 'authenticate']);
    Route::post('activate-account', [AuthController::class, 'activateAccount']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'invalidateAuth']);

    Route::group([
        'middleware' => [JWTAuth::class, PagesPermissions::class]
    ], function () {
        Route::get('stats', [BasicReportsController::class, 'getStats']);
        // Auth API's
        Route::post('get-auth', [AuthController::class, 'getAuth']);

        // My Space
        Route::group([
            'prefix' => 'my-area'
        ], function () {
            Route::post('change-password', [AuthController::class, 'changePassword']);

            // Service Documents
            Route::post('get-service-documents', [ServiceDocumentsController::class, 'getDocuments']);
            Route::resource('service-documents', ServiceDocumentsController::class);
        });

        // Anonymous Users
        Route::post('get-users', [UsersController::class, 'getUsers']);
        Route::post('get-user-logs', [UsersController::class, 'getUserLogs']);
        Route::post('clear-user-logs', [UsersController::class, 'clearUserLogs']);
        Route::post('reset-password', [UsersController::class, 'reset']);
        Route::resource('users', UsersController::class);

        // Resources
        Route::get('get-regions', [ResourcesController::class, 'getRegions']);
        Route::get('get-districts', [ResourcesController::class, 'getDistricts']);
        Route::get('get-cities', [ResourcesController::class, 'getCities']);
        Route::get('get-user-resources', [ResourcesController::class, 'getUserResources']);
        Route::post('get-area-reports', [UserSensorsController::class, 'getUserSensorValuesByArea']);

        // Notifications
        Route::post('notifications', [NotificationsController::class, 'sendNotifications']);
        Route::post('get-messages', [MessagesController::class, 'getMessages']);
        Route::resource('messages', MessagesController::class);

        // Boards
        Route::post('get-boards', [BoardsController::class, 'getBoards']);
        Route::post('get-board-pin-types', [BoardsController::class, 'getBoardPinTypes']);
        Route::resource('boards', BoardsController::class);
        // -------------------------- - - - - - ----------------------------- //
        Route::resource('user-boards', UserBoardsController::class);

        // Tickets
        Route::post('get-tickets', [TicketsController::class, 'getTickets']);
        Route::get('get-ticket-thread/{ticketId}', [TicketsController::class, 'getTicketThread']);
        Route::resource('tickets', TicketsController::class);

        // Sensors
        Route::post('get-sensors', [SensorsController::class, 'getSensors']);
        Route::post('get-sensor-pin-types', [SensorsController::class, 'getSensorPinTypes']);
        Route::resource('sensors', SensorsController::class);
        // -------------------------- - - - - - ----------------------------- //
        Route::post('switch-smart-actuator', [UserSensorsController::class, 'switchSmartActuator']);
        Route::get('user-sensors-auto-added', [UserSensorsController::class, 'getAutoAddedUserSensors']);
        Route::resource('user-sensors', UserSensorsController::class);
        Route::post('get-user-sensors', [UserSensorsController::class, 'getUserSensors']);
        Route::post('get-user-sensors-by-token', [UserSensorsController::class, 'getUserSensorsByToken']);
        Route::get('get-user-sensor-values', [UserSensorsController::class, 'getUserSensorValues']);
        Route::get('get-user-sensor-values/{id}', [UserSensorsController::class, 'getUserSensorValuesById']);

        // Actuators
        Route::post('get-actuators', [ActuatorsController::class, 'getActuators']);
        Route::post('get-actuator-pin-types', [ActuatorsController::class, 'getActuatorPinTypes']);
        Route::resource('actuators', ActuatorsController::class);
        // -------------------------- - - - - - ----------------------------- //
        Route::post('switch-actuator', [UserActuatorsController::class, 'switchActuator']);
        Route::resource('user-actuators', UserActuatorsController::class);

        // Pins
        Route::post('get-pin-types', [PinTypesController::class, 'getPinTypes']);
        Route::resource('pin-types', PinTypesController::class);

        // Automations
        Route::post('get-automations', [AutomationsController::class, 'getAutomations']);
        Route::resource('automations', AutomationsController::class);

        // Schedulers
        Route::post('get-schedulers', [SmartSchedulerController::class, 'getSchedulers']);
        Route::resource('schedulers', SmartSchedulerController::class);

        // Reports API's
        Route::get('get-user-brief-stats', [SmartReportsController::class, 'getUserBriefStats']);
        Route::get('get-brief-stats', [SmartReportsController::class, 'getBriefStats']);
        Route::get('get-health-status', [SmartReportsController::class, 'getHealthStatus']);
        Route::get('get-total-losses', [SmartReportsController::class, 'getUserTotalLosses']);
        Route::get('get-map-user-summary', [SmartReportsController::class, 'getMapUserSummary']);

        // Settings API's
        Route::group([
            'prefix' => 'user-boards'
        ], function () {
            // Page Access
            Route::resource('get-boards', PagesController::class);
            Route::post('get-pages', [PagesController::class, 'getPages']);

            // Roles
            Route::resource('roles', RolesController::class);
            Route::post('get-roles', [RolesController::class, 'getRoles']);

            // Permissions
            Route::resource('permissions', PermissionsController::class);
            Route::post('get-permissions', [PermissionsController::class, 'getPermissions']);
        });

        // Settings API's
        Route::group([
            'prefix' => 'settings'
        ], function () {
            // Documents
            Route::post('get-documents', [DocumentsController::class, 'getDocuments']);
            Route::resource('documents', DocumentsController::class);

            // Page Access
            Route::resource('pages', PagesController::class);
            Route::post('get-pages', [PagesController::class, 'getPages']);

            // Roles
            Route::resource('roles', RolesController::class);
            Route::post('get-roles', [RolesController::class, 'getRoles']);

            // Permissions
            Route::resource('permissions', PermissionsController::class);
            Route::post('get-permissions', [PermissionsController::class, 'getPermissions']);
        });

        // Seending API's
        Route::group([
            'prefix' => 'billing'
        ], function () {
            // Smart Meter
            Route::resource('payments', PaymentsController::class);
            Route::post('getPayments', [PaymentsController::class, 'getPayments']);
        });

        // Seending API's
        Route::group([
            'prefix' => 'seed'
        ], function () {
            // Smart Meter
            Route::post('smart-meter', [SeedController::class, 'smartMeter']);
            Route::post('smart-meter-import', [SeedController::class, 'importSensorData']);
        });
    });

    // Preferences can be accessable without authentication
    Route::post('preference-files', [PreferencesController::class, 'updatePreferenceFiles']);
    Route::resource('preferences', PreferencesController::class);

    Route::get('testSeq', [SeedController::class, 'generateDateSequence']);

});
