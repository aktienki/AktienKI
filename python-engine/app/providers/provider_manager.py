from app.providers.provider_registry import (
    PROVIDER_REGISTRY,
)


class ProviderManager:

    @classmethod
    def create(cls, scope):

        provider = PROVIDER_REGISTRY.get(scope)

        if provider is None:

            raise RuntimeError(
                f"No provider for {scope}"
            )

        return provider()