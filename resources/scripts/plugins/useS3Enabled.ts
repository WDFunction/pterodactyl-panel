import useSWR from 'swr';
import { getS3Enabled } from '@/api/server/files/s3Transfer';
import { ServerContext } from '@/state/server';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    const { data } = useSWR<boolean>(
        `${uuid}:s3-enabled`,
        () => getS3Enabled(uuid),
        {
            revalidateOnFocus: false,
            revalidateOnReconnect: false,
            dedupingInterval: 60000,
            errorRetryCount: 1,
        }
    );

    return data ?? false;
};
