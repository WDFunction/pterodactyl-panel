import http from '@/api/http';

interface S3UploadUrlResponse {
    upload_url: string;
    s3_key: string;
    transfer_id: string;
}

interface S3UploadCompleteResponse {
    status: string;
    transfer_id: string;
}

interface S3DownloadUrlResponse {
    transfer_id: string;
}

interface S3DownloadStatusResponse {
    status: 'pending' | 'processing' | 'completed' | 'failed';
    download_url?: string;
    error?: string;
}

interface S3EnabledResponse {
    enabled: boolean;
    icon_url: string;
}

export const getS3Enabled = async (uuid: string): Promise<boolean> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/s3-enabled`);
    return (data as S3EnabledResponse).enabled;
};

export const getS3IconUrl = async (uuid: string): Promise<string> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/s3-enabled`);
    return (data as S3EnabledResponse).icon_url ?? '';
};

export const getS3UploadUrl = async (
    uuid: string,
    filename: string,
    directory: string
): Promise<S3UploadUrlResponse> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/s3-upload-url`, {
        params: { filename, directory },
    });
    return data as S3UploadUrlResponse;
};

export const notifyS3UploadComplete = async (
    uuid: string,
    transferId: string
): Promise<S3UploadCompleteResponse> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/files/s3-upload-complete`, {
        transfer_id: transferId,
    });
    return data as S3UploadCompleteResponse;
};

export const requestS3Download = async (uuid: string, file: string): Promise<S3DownloadUrlResponse> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/s3-download-url`, {
        params: { file },
    });
    return data as S3DownloadUrlResponse;
};

export const getS3DownloadStatus = async (uuid: string, transferId: string): Promise<S3DownloadStatusResponse> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/s3-download-status`, {
        params: { transfer_id: transferId },
    });
    return data as S3DownloadStatusResponse;
};
