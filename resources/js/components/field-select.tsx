import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type FieldSelectOption = {
    value: string;
    label: string;
};

type Props = {
    value: string;
    onValueChange: (value: string) => void;
    options: FieldSelectOption[];
    placeholder?: string;
    /** Form field name — rendered as hidden input (empty when value is ""). */
    name?: string;
    disabled?: boolean;
    className?: string;
    triggerClassName?: string;
    size?: 'sm' | 'default';
    /** Prefer "top" when select sits near bottom of viewport (composer). */
    side?: 'top' | 'bottom' | 'left' | 'right';
};

/**
 * shadcn Select with optional hidden input for native form posts.
 * Use value="" for the empty/all option; Radix maps it via EMPTY_SENTINEL.
 */
const EMPTY = '__empty__';

export function FieldSelect({
    value,
    onValueChange,
    options,
    placeholder = 'Pilih…',
    name,
    disabled,
    className,
    triggerClassName,
    size = 'default',
    side = 'bottom',
}: Props) {
    const radixValue = value === '' ? EMPTY : value;

    return (
        <div className={cn('w-full', className)}>
            {name ? (
                <input
                    type="hidden"
                    name={name}
                    value={value}
                    disabled={disabled}
                />
            ) : null}
            <Select
                value={radixValue}
                onValueChange={(v) => onValueChange(v === EMPTY ? '' : v)}
                disabled={disabled}
            >
                <SelectTrigger
                    size={size}
                    className={cn(
                        'w-full border-border/70 bg-background shadow-none',
                        triggerClassName,
                    )}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent
                    side={side}
                    position="popper"
                    className="z-50 max-h-64"
                >
                    {options.map((opt) => (
                        <SelectItem
                            key={opt.value === '' ? EMPTY : opt.value}
                            value={opt.value === '' ? EMPTY : opt.value}
                        >
                            {opt.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
