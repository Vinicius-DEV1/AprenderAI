import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../api/axios';
import { 
    ApiPricingEntry, 
    EditForm, 
    CreateForm, 
    VaultEntry, 
    DiscoveredModel,
    ApiPricingLog
} from './Types';

export const useApiPricing = () => {
    const queryClient = useQueryClient();

    // States for Editing
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editForm, setEditForm] = useState<EditForm>({ input_price_per_1m: '', output_price_per_1m: '' });
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingUpdate, setPendingUpdate] = useState<{ id: number; form: EditForm } | null>(null);
    const [logsOpen, setLogsOpen] = useState<number | null>(null);

    // States for Creating
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [createTab, setCreateTab] = useState<'manual' | 'api'>('manual');
    const [createForm, setCreateForm] = useState<CreateForm>({
        api_name: '',
        model_key: '',
        input_price_per_1m: '',
        output_price_per_1m: ''
    });

    // States for API Discovery
    const [selectedVaultId, setSelectedVaultId] = useState<string>('');
    const [isDiscovering, setIsDiscovering] = useState(false);
    const [discoveredModels, setDiscoveredModels] = useState<DiscoveredModel[]>([]);

    // ----------------------------------------------------
    // Queries
    // ----------------------------------------------------
    const { data: pricing = [], isLoading } = useQuery({
        queryKey: ['admin-api-pricing'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-pricing');
            return res.data.data as ApiPricingEntry[];
        }
    });

    const { data: logsData, isLoading: logsLoading } = useQuery({
        queryKey: ['admin-api-pricing-logs', logsOpen],
        queryFn: async () => {
            if (!logsOpen) return [];
            const res = await api.get(`/api/v1/admin/api-pricing/${logsOpen}/logs`);
            return res.data.data as ApiPricingLog[];
        },
        enabled: !!logsOpen,
    });

    const { data: vaultsData } = useQuery({
        queryKey: ['admin-api-pricing-vaults'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-pricing/vaults');
            return res.data.data as VaultEntry[];
        },
        enabled: createModalOpen && createTab === 'api'
    });

    // ----------------------------------------------------
    // Mutations
    // ----------------------------------------------------
    const updateMutation = useMutation({
        mutationFn: async ({ id, form }: { id: number; form: EditForm }) => {
            const res = await api.put(`/api/v1/admin/api-pricing/${id}`, {
                input_price_per_1m: parseFloat(form.input_price_per_1m),
                output_price_per_1m: parseFloat(form.output_price_per_1m),
            });
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-pricing'] });
            queryClient.invalidateQueries({ queryKey: ['admin-api-pricing-logs'] });
            toast.success(data.message || 'Preço atualizado com sucesso!');
            setEditingId(null);
            setConfirmOpen(false);
            setPendingUpdate(null);
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || err.message || 'Erro ao atualizar preço.';
            toast.error(msg);
        }
    });

    const createMutation = useMutation({
        mutationFn: async (form: CreateForm) => {
            const res = await api.post('/api/v1/admin/api-pricing', {
                api_name: form.api_name,
                model_key: form.model_key,
                input_price_per_1m: parseFloat(form.input_price_per_1m),
                output_price_per_1m: parseFloat(form.output_price_per_1m),
            });
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-pricing'] });
            toast.success(data.message || 'Preço cadastrado com sucesso!');
            setCreateModalOpen(false);
            resetCreateForm();
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || err.message || 'Erro ao cadastrar preço.';
            toast.error(msg);
        }
    });

    // ----------------------------------------------------
    // Actions
    // ----------------------------------------------------
    const openEdit = (entry: ApiPricingEntry) => {
        setEditingId(entry.id);
        setEditForm({
            input_price_per_1m: String(entry.input_price_per_1m),
            output_price_per_1m: String(entry.output_price_per_1m),
        });
    };

    const requestSave = () => {
        if (!editingId) return;
        const inputVal = parseFloat(editForm.input_price_per_1m);
        const outputVal = parseFloat(editForm.output_price_per_1m);
        if (isNaN(inputVal) || inputVal < 0 || isNaN(outputVal) || outputVal < 0) {
            toast.error('Valores devem ser números não-negativos.');
            return;
        }
        setPendingUpdate({ id: editingId, form: editForm });
        setConfirmOpen(true);
    };

    const confirmSave = () => {
        if (pendingUpdate) {
            updateMutation.mutate(pendingUpdate);
        }
    };

    const resetCreateForm = () => {
        setCreateForm({
            api_name: '',
            model_key: '',
            input_price_per_1m: '',
            output_price_per_1m: ''
        });
        setSelectedVaultId('');
        setDiscoveredModels([]);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const inputVal = parseFloat(createForm.input_price_per_1m);
        const outputVal = parseFloat(createForm.output_price_per_1m);

        if (!createForm.api_name || !createForm.model_key) {
            toast.error('Provedor e Modelo são obrigatórios.');
            return;
        }
        if (isNaN(inputVal) || inputVal < 0 || isNaN(outputVal) || outputVal < 0) {
            toast.error('Os preços devem ser números positivos.');
            return;
        }

        createMutation.mutate(createForm);
    };

    const discoverModelsFromVault = async () => {
        if (!selectedVaultId) return;
        setIsDiscovering(true);
        setDiscoveredModels([]);
        try {
            const res = await api.post('/api/v1/admin/api-keys/discover', { vault_id: selectedVaultId });
            if (res.data.is_valid && res.data.models) {
                setDiscoveredModels(res.data.models);
                toast.success(`${res.data.models.length} modelos encontrados!`);
            } else {
                toast.error(res.data.error || 'Nenhum modelo retornado ou chave inválida.');
            }
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Erro ao buscar modelos na API.');
        } finally {
            setIsDiscovering(false);
        }
    };

    const handleSelectDiscoveredModel = (modelId: string) => {
        const vault = vaultsData?.find(v => v.id.toString() === selectedVaultId);
        if (vault) {
            const providerNames: Record<string, string> = {
                'openai': 'OpenAI',
                'gemini': 'Google Gemini',
                'grok': 'xAI'
            };
            setCreateForm({
                ...createForm,
                api_name: providerNames[vault.provider] || vault.provider,
                model_key: modelId
            });
        }
    };

    return {
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
    };
};
