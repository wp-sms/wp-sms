import type { UseMutationOptions } from '@tanstack/react-query';

export type SaveSettingsValuesBody = Record<string, any>;

export type SaveSettingsValuesResponse = {
  success: boolean;
  data: {
    saved_keys: string[];
  };
};

export type UseSaveSettingsValuesType = {
  options?: UseMutationOptions<SaveSettingsValuesResponse, Error, SaveSettingsValuesBody, unknown>;
  body: SaveSettingsValuesBody;
  response: SaveSettingsValuesResponse;
};
