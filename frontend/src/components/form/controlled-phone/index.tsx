import { WordPressDataService } from '@/core/config/dataService';
import { Controller, useFormContext } from 'react-hook-form';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import type { ControlledPhoneProps, Country } from './types';
import parsePhoneNumber from 'libphonenumber-js';
import { CustomSkeleton } from '@/components/ui/custom-skeleton';
import { FieldDescription } from '../description';
import { FieldMessage } from '../message';
import { FieldLabel } from '../label';

export const ControlledPhone: React.FC<ControlledPhoneProps> = ({ name, label, description, isLoading = false }) => {
    const { control } = useFormContext();
    const [jsonData, setJsonData] = useState<Country[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);

    const [selectedCountry, setSelectedCountry] = useState<Country | undefined>(undefined);

    const dataService = WordPressDataService.getInstance();

    useEffect(() => {
        const loadData = async () => {
            try {
                setLoading(true);

                const response = await fetch(`${dataService.getBuildUrl()}countries.json`);
                const importedData = (await response.json()) as Country[];

                setJsonData(importedData);

                setSelectedCountry(importedData?.find((item) => item.code === 'US'));
            } catch (error) {
            } finally {
                setLoading(false);
            }
        };

        loadData();
    }, []);

    return (
        <Controller
            name={name ?? ''}
            control={control}
            defaultValue={'+1'}
            render={({ field, fieldState }) => {
                const phoneUtil = parsePhoneNumber(`+${field?.value}`);

                return (
                    <div className="flex flex-col gap-y-1.5">
                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldLabel text={label} />
                        </CustomSkeleton>

                        <div className="flex border border-border rounded-lg focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px] transition-all duration-300">
                            <div>
                                <Popover open={open} onOpenChange={setOpen}>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            role="combobox"
                                            aria-expanded={open}
                                            className="w-20 justify-between rounded-r-none bg-accent"
                                        >
                                            {`${selectedCountry?.emoji} ${selectedCountry?.dialCode}`}
                                        </Button>
                                    </PopoverTrigger>

                                    <PopoverContent className="w-auto p-0">
                                        <Command>
                                            <CommandInput placeholder="Search country..." />
                                            <CommandList>
                                                <CommandEmpty>No country found.</CommandEmpty>

                                                {jsonData?.map((item) => (
                                                    <CommandItem
                                                        key={`command-item-${item.id}`}
                                                        value={`${item.dialCode}__${item.code}__${item.name}`}
                                                        className="flex justify-between gap-1"
                                                        onSelect={(value) => {
                                                            const [dialCode, code] = value?.split('__');

                                                            const finalValue = `${dialCode.replace('+', '')}${phoneUtil?.nationalNumber}`;

                                                            setSelectedCountry(
                                                                jsonData?.find(
                                                                    (item) =>
                                                                        item?.code === code &&
                                                                        item.dialCode === dialCode
                                                                )
                                                            );

                                                            field?.onChange(finalValue);

                                                            setOpen(false);
                                                        }}
                                                    >
                                                        <div className="flex items-center gap-x-1">
                                                            <div>{item?.emoji}</div>
                                                            <span className="line-clamp-1">{item?.name}</span>
                                                        </div>

                                                        <span className="text-muted-foreground">{item?.dialCode}</span>
                                                    </CommandItem>
                                                ))}
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            <Input
                                value={field?.value?.replace(phoneUtil?.countryCallingCode, '')}
                                className="border-none focus:border-0 focus-visible:border-0 focus-within:!border-0 focus-visible:ring-0 focus-within:ring-0"
                                onChange={(e) => {
                                    field.onChange(`${phoneUtil?.countryCallingCode}${e.target.value}`);
                                }}
                            />
                        </div>

                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldDescription text={description} />
                        </CustomSkeleton>

                        <FieldMessage text={fieldState?.error?.message} />
                    </div>
                );
            }}
        />
    );
};
