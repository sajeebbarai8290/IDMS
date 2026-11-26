<?php

namespace App\Modules\Warehouse\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier administrative jurisdiction, 4-eye Separation of Duties (SoD),
 * ROLE_SUPER_ADMIN operational blackout on transactional stock operations,
 * and ROLE_EXTERNAL_PARTNER anonymization for Module 08 (Warehouse & Inventory).
 */
class WarehousePolicy
{
    protected const FACILITY_VIEW_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    protected const FACILITY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const ITEM_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DRRO',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    protected const STOCK_VIEW_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    protected const STOCK_RECEIVE_ROLES = [
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    protected const STOCK_VERIFY_RECEIPT_ROLES = [
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    protected const STOCK_TRANSFER_ROLES = [
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    protected const STOCK_TRANSFER_APPROVE_ROLES = [
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const STOCK_ISSUE_ROLES = [
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    public const STOCK_WRITEOFF_ROLES = [
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
    ];

    protected const LEDGER_EXPORT_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewAnyFacilities(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.facility.view',
            self::FACILITY_VIEW_ROLES,
            'You are not authorized to view warehouse facilities.'
        );
    }

    public function viewFacility(User $user, Warehouse $warehouse): Response
    {
        $roleCheck = $this->viewAnyFacilities($user);
        if (! $roleCheck->allowed()) {
            return $roleCheck;
        }

        // Warehouse may also be visible if it supplies the user's jurisdiction (upstream depot)
        $warehouse->loadMissing('administrativeUnit');
        $targetPath = $warehouse->administrativeUnit?->ancestry_path ?? '/';

        if ($this->scopeService->canAccessScope($user, $targetPath) || $this->isUpstreamDepot($user, $targetPath)) {
            return Response::allow();
        }

        return Response::deny('Jurisdiction access denied: warehouse falls outside your administrative hierarchy.');
    }

    public function createFacility(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.facility.manage',
            self::FACILITY_MANAGE_ROLES,
            'Your role is not authorized to register or configure warehouse facilities.'
        );
    }

    public function updateFacility(User $user, Warehouse $warehouse): Response
    {
        $permCheck = $this->createFacility($user);
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        $warehouse->loadMissing('administrativeUnit');

        return $this->checkScope($user, $warehouse->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageItems(User $user, ?Commodity $commodity = null): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.facility.manage',
            self::ITEM_MANAGE_ROLES,
            'Your role is not authorized to create or update inventory item definitions.'
        );
    }

    public function viewAnyStock(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.view',
            self::STOCK_VIEW_ROLES,
            'You are not authorized to view warehouse inventory and stock movements.'
        );
    }

    public function viewStock(User $user, Warehouse $warehouse): Response
    {
        $roleCheck = $this->viewAnyStock($user);
        if (! $roleCheck->allowed()) {
            return $roleCheck;
        }

        $warehouse->loadMissing('administrativeUnit');
        $targetPath = $warehouse->administrativeUnit?->ancestry_path ?? '/';

        if ($this->scopeService->canAccessScope($user, $targetPath) || $this->isUpstreamDepot($user, $targetPath)) {
            return Response::allow();
        }

        return Response::deny('Jurisdiction access denied: warehouse stock falls outside your administrative hierarchy.');
    }

    public function receiveStock(User $user, ?Warehouse $warehouse = null): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'receive stock or create goods receipt notes')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.receive',
            self::STOCK_RECEIVE_ROLES,
            'Your role is not authorized to receive incoming supplies or record GRNs.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        if ($warehouse) {
            $warehouse->loadMissing('administrativeUnit');

            return $this->checkScope($user, $warehouse->administrativeUnit?->ancestry_path ?? '/');
        }

        return Response::allow();
    }

    public function verifyReceipt(User $user, StockReceipt $receipt): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'verify or reject goods receipt notes')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.receive',
            self::STOCK_VERIFY_RECEIPT_ROLES,
            'Your role is not authorized to verify goods receipt notes.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        $receipt->loadMissing('warehouse.administrativeUnit');
        $scopeCheck = $this->checkScope($user, $receipt->warehouse?->administrativeUnit?->ancestry_path ?? '/');
        if (! $scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($receipt->received_by === $user->id) {
            return Response::deny('Four-eyes separation of duties: the receiving officer cannot self-verify their own GRN.');
        }

        return Response::allow();
    }

    public function viewReceipt(User $user, StockReceipt $receipt): Response
    {
        $receipt->loadMissing('warehouse.administrativeUnit');

        return $this->viewStock($user, $receipt->warehouse);
    }

    public function requestTransfer(User $user, ?Warehouse $sourceWarehouse = null, ?Warehouse $destinationWarehouse = null): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'initiate inter-warehouse stock transfers')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.transfer',
            self::STOCK_TRANSFER_ROLES,
            'Your role is not authorized to create inter-warehouse transfer requests.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        if ($sourceWarehouse && $destinationWarehouse) {
            $sourceWarehouse->loadMissing('administrativeUnit');
            $destinationWarehouse->loadMissing('administrativeUnit');

            $canAccessSource = $this->scopeService->canAccessScope($user, $sourceWarehouse->administrativeUnit?->ancestry_path ?? '/');
            $canAccessDest = $this->scopeService->canAccessScope($user, $destinationWarehouse->administrativeUnit?->ancestry_path ?? '/');

            if (! $canAccessSource && ! $canAccessDest) {
                return Response::deny('Jurisdiction access denied: you must have jurisdiction over either the source or destination warehouse.');
            }
        }

        return Response::allow();
    }

    public function viewTransfer(User $user, WarehouseTransfer $transfer): Response
    {
        $roleCheck = $this->viewAnyStock($user);
        if (! $roleCheck->allowed()) {
            return $roleCheck;
        }

        $transfer->loadMissing(['sourceWarehouse.administrativeUnit', 'destinationWarehouse.administrativeUnit']);
        $srcPath = $transfer->sourceWarehouse?->administrativeUnit?->ancestry_path ?? '/';
        $dstPath = $transfer->destinationWarehouse?->administrativeUnit?->ancestry_path ?? '/';

        if ($this->scopeService->canAccessScope($user, $srcPath) || $this->scopeService->canAccessScope($user, $dstPath)) {
            return Response::allow();
        }

        return Response::deny('Jurisdiction access denied: transfer falls outside your administrative hierarchy.');
    }

    public function approveTransfer(User $user, WarehouseTransfer $transfer): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'approve or cancel inter-warehouse stock transfers')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.transfer',
            self::STOCK_TRANSFER_APPROVE_ROLES,
            'Your role is not authorized to approve inter-warehouse transfers.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        $transfer->loadMissing(['sourceWarehouse.administrativeUnit', 'destinationWarehouse.administrativeUnit']);
        $srcPath = $transfer->sourceWarehouse?->administrativeUnit?->ancestry_path ?? '/';
        $dstPath = $transfer->destinationWarehouse?->administrativeUnit?->ancestry_path ?? '/';

        if (! $this->scopeService->canAccessScope($user, $srcPath) && ! $this->scopeService->canAccessScope($user, $dstPath)) {
            return Response::deny('Jurisdiction access denied: transfer falls outside your administrative hierarchy.');
        }

        if ($transfer->requested_by === $user->id) {
            return Response::deny('Four-eyes separation of duties: you cannot approve your own stock transfer request.');
        }

        return Response::allow();
    }

    public function dispatchTransfer(User $user, WarehouseTransfer $transfer): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'dispatch inter-warehouse transfers')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.transfer',
            self::STOCK_TRANSFER_ROLES,
            'Your role is not authorized to dispatch inter-warehouse transfers.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        $transfer->loadMissing('sourceWarehouse.administrativeUnit');

        return $this->checkScope($user, $transfer->sourceWarehouse?->administrativeUnit?->ancestry_path ?? '/');
    }

    public function receiveTransfer(User $user, WarehouseTransfer $transfer): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'confirm receipt of inter-warehouse transfers')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.transfer',
            self::STOCK_TRANSFER_ROLES,
            'Your role is not authorized to confirm receipt of inter-warehouse transfers.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        $transfer->loadMissing('destinationWarehouse.administrativeUnit');

        return $this->checkScope($user, $transfer->destinationWarehouse?->administrativeUnit?->ancestry_path ?? '/');
    }

    public function issueStock(User $user, ?Warehouse $warehouse = null): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'issue stock against relief allocations')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.issue',
            self::STOCK_ISSUE_ROLES,
            'Your role is not authorized to issue stock from warehouses.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        if ($warehouse) {
            $warehouse->loadMissing('administrativeUnit');
            $targetPath = $warehouse->administrativeUnit?->ancestry_path ?? '/';

            if (! $this->scopeService->canAccessScope($user, $targetPath) && ! $this->isUpstreamDepot($user, $targetPath)) {
                return Response::deny('Jurisdiction access denied: warehouse falls outside your operational authority.');
            }
        }

        return Response::allow();
    }

    public function writeOffOrAdjust(User $user, Warehouse $warehouse, ?User $approvingOfficer = null, bool $requiresDualAuth = true): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'perform stock adjustments or write-offs')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.writeoff',
            self::STOCK_WRITEOFF_ROLES,
            'Your role is not authorized to authorize stock adjustments or damaged/expired write-offs.'
        );
        if (! $permCheck->allowed()) {
            return $permCheck;
        }

        $warehouse->loadMissing('administrativeUnit');
        $scopeCheck = $this->checkScope($user, $warehouse->administrativeUnit?->ancestry_path ?? '/');
        if (! $scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($requiresDualAuth) {
            if (! $approvingOfficer) {
                return Response::deny('Dual authorization required: a co-authorizing officer (approved_by) must verify stock write-offs and negative adjustments.');
            }

            if ($approvingOfficer->id === $user->id) {
                return Response::deny('Four-eyes separation of duties: co-authorizing officer cannot be the same user submitting the write-off.');
            }

            if ($approvingOfficer->hasRole('ROLE_SUPER_ADMIN')) {
                return Response::deny('Super Admin cannot act as co-authorizer for stock write-offs.');
            }

            $coAuthCheck = $this->allowIfHasPermissionOrRoles(
                $approvingOfficer,
                'warehouse.stock.writeoff',
                self::STOCK_WRITEOFF_ROLES,
                'Selected co-authorizer lacks write-off authorization role.'
            );
            if (! $coAuthCheck->allowed()) {
                return $coAuthCheck;
            }
        }

        return Response::allow();
    }

    public function exportLedger(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'warehouse.stock.export_ledger',
            self::LEDGER_EXPORT_ROLES,
            'Your role is not authorized to export warehouse stock ledgers.'
        );
    }

    private function isUpstreamDepot(User $user, string $warehouseAncestryPath): bool
    {
        if ($user->isNationalScope()) {
            return true;
        }

        $userPath = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
        $whPath = rtrim($warehouseAncestryPath, '/') . '/';

        return str_starts_with($userPath, $whPath);
    }

    private function denySuperAdmin(User $user, string $action): ?Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny(
                "Super Admin operational blackout: ROLE_SUPER_ADMIN cannot {$action}."
            );
        }

        return null;
    }

    private function checkScope(User $user, string $targetAncestryPath): Response
    {
        if ($this->scopeService->canAccessScope($user, $targetAncestryPath)) {
            return Response::allow();
        }

        return Response::deny('Jurisdiction access denied: record falls outside your administrative hierarchy.');
    }

    private function allowIfHasPermissionOrRoles(User $user, string $permission, array $roles, string $denyMessage): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return in_array('ROLE_SUPER_ADMIN', $roles, true)
                ? Response::allow()
                : Response::deny($denyMessage);
        }

        if ($user->hasPermission($permission)) {
            return Response::allow();
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return Response::allow();
            }
        }

        return Response::deny($denyMessage);
    }
}
