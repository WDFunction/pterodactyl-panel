import useSWR from 'swr';
import { getS3IconUrl } from '@/api/server/files/s3Transfer';
import { ServerContext } from '@/state/server';

export default (): string | undefined => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    const { data } = useSWR<string>(
        `${uuid}:s3-icon-url`,
        () => getS3IconUrl(uuid),
        {
            revalidateOnFocus: false,
            revalidateOnReconnect: false,
            dedupingInterval: 60000,
            errorRetryCount: 1,
        }
    );

    return data || undefined;
};
