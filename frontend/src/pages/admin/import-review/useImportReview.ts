/**
 * useImportReview Hook
 * Manages state, queries, and mutations for the question review and cropping process.
 */
import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import api from '../../../api/axios';
import { toast } from 'sonner';

export function useImportReview() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const queryClient = useQueryClient();

  // --- UI State ---
  const [activeTarget, setActiveTarget] = useState<string>('statement');
  const [saving, setSaving] = useState(false);
  const [lastCropAt, setLastCropAt] = useState<number>(Date.now());
  
  /** Ref map: imageId -> <img> element (react-cropper attaches .cropper properties) */
  const cropperEls = useRef<Record<number, any>>({});

  // --- Queries ---
  const { data, isLoading } = useQuery({
    queryKey: ['admin-import-review', id, Object.fromEntries(searchParams)],
    queryFn: async () => {
      const res = await api.get(`/api/v1/admin/import/review/${id}`, { 
        params: Object.fromEntries(searchParams) 
      });
      return res.data;
    }
  });

  const { data: historyData } = useQuery({
    queryKey: ['admin-import-question-history', id],
    queryFn: async () => {
      const res = await api.get(`/api/v1/admin/questions/${id}/triage-history`);
      return res.data;
    }
  });

  // --- Mutations ---
  const approveMutation = useMutation({
    mutationFn: async () => {
      const res = await api.post(`/api/v1/admin/import/review/${id}/approve`, Object.fromEntries(searchParams));
      return res.data;
    },
    onSuccess: (data) => {
      if (data.next_id) {
        navigate(`/admin/import/review/${data.next_id}?${searchParams.toString()}`);
        toast.success('Question approved! Moving to next...');
      } else {
        navigate(`/admin/import/review?${searchParams.toString()}`);
        toast.success('Question approved! Queue completed.');
      }
    },
    onError: () => toast.error('Failed to approve question.')
  });

  const revertMutation = useMutation({
    mutationFn: async () => {
      return await api.post(`/api/v1/admin/import/review/${id}/revert`);
    },
    onSuccess: () => {
      toast.success('Question returned for review.');
      navigate(`/admin/import/review?${searchParams.toString()}`);
    },
    onError: () => toast.error('Failed to revert question.')
  });

  const deleteImageMutation = useMutation({
    mutationFn: async (imageId: number) => {
      return await api.delete(`/api/v1/admin/import/review/${imageId}/image`);
    },
    onSuccess: () => {
      toast.success('Image removed.');
      queryClient.invalidateQueries({ queryKey: ['admin-import-review', id] });
    }
  });

  // --- Handlers ---
  const handleSaveCrop = async (targetOverride?: string, imageIdOverride?: number) => {
    const target = targetOverride || activeTarget;
    const currentImageId = imageIdOverride ?? data?.question?.images?.[0]?.id;

    if (!currentImageId) {
      toast.error('No image found for cropping.');
      return;
    }

    const el = cropperEls.current[currentImageId];
    const activeCropper = el?.cropper;

    if (!activeCropper) {
      toast.error('Crop editor not initialized.');
      return;
    }

    let imageData: any;
    try {
      imageData = activeCropper.getData();
    } catch (err) {
      toast.error('Error reading crop area.');
      return;
    }

    const x = Math.round(imageData.x);
    const y = Math.round(imageData.y);
    const width = Math.round(imageData.width);
    const height = Math.round(imageData.height);

    setSaving(true);
    try {
      await api.post(`/api/v1/admin/import/review/${currentImageId}/crop`, {
        target, x, y, width, height,
      });
      toast.success('Crop saved successfully!');
      setLastCropAt(Date.now());
      
      queryClient.invalidateQueries({ queryKey: ['admin-import-review', id] });

      if (target !== 'statement') {
        const nextMap: any = { 'A': 'B', 'B': 'C', 'C': 'D', 'D': 'E', 'E': 'E' };
        setActiveTarget(nextMap[target] || 'A');
      }
    } catch (error: any) {
      const msg = error?.response?.data?.message || 'Error saving crop.';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  const isComplete = (q: any) => {
    if (!q) {
      return {
        total: false,
        hasExplanation: false,
        hasReasoning: false,
        hasDifficulty: false,
        hasSubject: false,
        hasTopic: false,
      };
    }
    const hasExplanation = !!(q.explanation && q.explanation.trim().length > 0);
    const hasReasoning = !!(q.difficulty_reasoning && q.difficulty_reasoning.trim().length > 0);
    const hasDifficulty = !!q.difficulty;
    const hasSubject = !!(q.subjects && q.subjects.length > 0);
    const hasTopic = !!(q.topics && q.topics.length > 0);
    
    return {
      total: !!(hasExplanation && hasReasoning && hasDifficulty && hasSubject && hasTopic),
      hasExplanation,
      hasReasoning,
      hasDifficulty,
      hasSubject,
      hasTopic
    };
  };

  const getCacheBustedUrl = (url: string) => {
    if (!url) return '';
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}t=${lastCropAt}`;
  };

  const baseApiUrl = (
    import.meta.env.VITE_API_URL || 
    import.meta.env.VITE_API_BASE_URL || 
    (import.meta.env.PROD ? '' : 'http://localhost:8000')
  ).replace(/\/$/, '');

  return {
    id,
    navigate,
    searchParams,
    data,
    isLoading,
    historyData,
    activeTarget,
    setActiveTarget,
    saving,
    cropperEls,
    approveMutation,
    revertMutation,
    deleteImageMutation,
    handleSaveCrop,
    isComplete,
    getCacheBustedUrl,
    apiUrl: baseApiUrl
  };
}
