import { Button } from '@/Components/ui/button';
import { RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function SyncButton({ children = 'MF同期', className, ...props }) {
    return (
        <Button
            variant="outline"
            className={cn(
                'flex items-center gap-2 border-indigo-500 text-indigo-600 hover:bg-indigo-50',
                className
            )}
            {...props}
        >
            <RefreshCw className="h-4 w-4" />
            {children}
        </Button>
    );
}
