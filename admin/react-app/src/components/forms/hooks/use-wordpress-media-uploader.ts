// Custom hook for WordPress media uploader
export function useWordPressMediaUploader() {
  const openMediaUploader = (callback: (url: string) => void) => {
    // Check if wp.media is available (WordPress media uploader)
    if (typeof window !== 'undefined' && (window as any).wp && (window as any).wp.media) {
      try {
        const mediaUploader = (window as any).wp.media({
          title: 'Select Image',
          button: {
            text: 'Use this image'
          },
          multiple: false
        })

        mediaUploader.on('select', () => {
          const attachment = mediaUploader.state().get('selection').first().toJSON()
          callback(attachment.url)
        })

        mediaUploader.open()
      } catch (error) {
        console.error('Error opening WordPress media uploader:', error)
        // Fallback to file input
        openFileInput(callback)
      }
    } else {
      console.warn('WordPress media uploader not available, using fallback')
      // Fallback: create a file input
      openFileInput(callback)
    }
  }

  const openFileInput = (callback: (url: string) => void) => {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.style.display = 'none'
    
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) {
        // Create a temporary URL for the file
        const url = URL.createObjectURL(file)
        callback(url)
        
        // Clean up the temporary URL after a delay
        setTimeout(() => URL.revokeObjectURL(url), 1000)
      }
      
      // Clean up the input
      if (document.body.contains(input)) {
        document.body.removeChild(input)
      }
    }
    
    document.body.appendChild(input)
    input.click()
  }

  return { openMediaUploader }
} 