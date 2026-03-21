import React from 'react';
import { useApiPricing } from './api-pricing/useApiPricing';
import ApiPricingHeader from './api-pricing/ApiPricingHeader';
import WarningBanner from './api-pricing/WarningBanner';
import PricingTable from './api-pricing/PricingTable';
import CreatePriceModal from './api-pricing/CreatePriceModal';
import ConfirmEditModal from './api-pricing/ConfirmEditModal';

export default function ApiPricing() {
    const {
        editingId,
        setEditingId,
        editForm,
        setEditForm,
        confirmOpen,
        setConfirmOpen,
        pendingUpdate,
        logsOpen,
        setLogsOpen,
        createModalOpen,
        setCreateModalOpen,
        createTab,
        setCreateTab,
        createForm,
        setCreateForm,
        selectedVaultId,
        setSelectedVaultId,
        isDiscovering,
        discoveredModels,
        pricing,
        isLoading,
        logsData,
        logsLoading,
        vaultsData,
        updateMutation,
        createMutation,
        openEdit,
        requestSave,
        confirmSave,
        resetCreateForm,
        handleCreateSubmit,
        discoverModelsFromVault,
        handleSelectDiscoveredModel
    } = useApiPricing();

    if (isLoading) {
        return (
            <div className="p-8 flex justify-center items-center">
                <div className="flex items-center gap-3 text-slate-500 font-medium">
                    <div className="w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                    Carregando configurações de preço...
                </div>
            </div>
        );
    }

    return (
        <div className="p-4 md:p-8 max-w-6xl mx-auto space-y-8">
            <ApiPricingHeader 
                resetCreateForm={resetCreateForm}
                setCreateModalOpen={setCreateModalOpen}
            />

            <WarningBanner />

            <PricingTable 
                pricing={pricing}
                editingId={editingId}
                editForm={editForm}
                setEditForm={setEditForm}
                setEditingId={setEditingId}
                openEdit={openEdit}
                requestSave={requestSave}
                updateMutationPending={updateMutation.isPending}
                logsOpen={logsOpen}
                setLogsOpen={setLogsOpen}
                logsLoading={logsLoading}
                logsData={logsData}
            />

            <CreatePriceModal 
                isOpen={createModalOpen}
                setOpen={setCreateModalOpen}
                createTab={createTab}
                setCreateTab={setCreateTab}
                createForm={createForm}
                setCreateForm={setCreateForm}
                selectedVaultId={selectedVaultId}
                setSelectedVaultId={setSelectedVaultId}
                vaultsData={vaultsData}
                discoverModels={discoverModelsFromVault}
                isDiscovering={isDiscovering}
                discoveredModels={discoveredModels}
                onSelectDiscovered={handleSelectDiscoveredModel}
                onSubmit={handleCreateSubmit}
                isPending={createMutation.isPending}
            />

            <ConfirmEditModal 
                isOpen={confirmOpen}
                setOpen={setConfirmOpen}
                pendingUpdate={pendingUpdate}
                modelKey={pricing.find(p => p.id === pendingUpdate?.id)?.model_key}
                onConfirm={confirmSave}
                isPending={updateMutation.isPending}
            />
        </div>
    );
}
